<?php
// Caminho: faz_bem_v2/app/Security.php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/Env.php';
    Env::load(__DIR__ . '/../.env');
    $ambiente = $_ENV['AMBIENTE'] ?? 'producao';
    
    // Detecção de ambiente local com base no host
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '192.168.') !== false || strpos($host, '10.') !== false);
    
    $secureCookie = (($ambiente === 'producao') && !$isLocalhost) || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    $samesiteCookie = (($ambiente === 'producao') && !$isLocalhost) ? 'None' : 'Lax';
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => $samesiteCookie
    ]);
    session_start();
}

class Security {
    /**
     * Gera e retorna um Token CSRF para a sessão atual.
     */
    public static function getCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

   
    public static function checkCSRF() {
        if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        $headers = array_change_key_case($headers, CASE_LOWER);
        $tokenEnviado = $headers['x-csrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($tokenEnviado) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de segurança CSRF inválido ou ausente.']);
            exit;
        }
    }

    /**
     * Valida se a sessão autenticada continua íntegra em relação à versão de sessão do usuário (invalidação pós-reset de senha).
     */
    public static function validateActiveSession(): bool {
        if (empty($_SESSION['usuario_id'])) {
            return true;
        }

        require_once __DIR__ . '/Database.php';
        try {
            $pdo = Database::getConexao();
            $stmt = $pdo->prepare("SELECT session_version FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $_SESSION['usuario_id']]);
            $row = $stmt->fetch();

            if (!$row) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_regenerate_id(true);
                }
                return false;
            }

            $dbVersion = (int) ($row['session_version'] ?? 1);
            if (!isset($_SESSION['session_version'])) {
                $_SESSION['session_version'] = $dbVersion;
                return true;
            }

            if ((int) $_SESSION['session_version'] !== $dbVersion) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_regenerate_id(true);
                }
                return false;
            }
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * @param int $maxAttempts Quantidade máxima de requisições permitidas
     * @param int $decaySeconds Tempo em segundos para limpar o registro
     */
    public static function checkRateLimit($maxAttempts = 10, $decaySeconds = 60) {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown_ip'), 0, 45);
        $endpoint = substr((string) ($_SERVER['SCRIPT_NAME'] ?? 'cli'), 0, 255);
        
        require_once __DIR__ . '/Database.php';
        try {
            $pdo = Database::getConexao();
            $now = time();
            $iniciadoAqui = !$pdo->inTransaction();
            if ($iniciadoAqui) {
                $pdo->beginTransaction();
            }

            if (mt_rand(1, 20) === 1) {
                $stmtClean = $pdo->prepare("DELETE FROM rate_limits WHERE first_attempt < ?");
                $stmtClean->execute([$now - max($decaySeconds, 3600)]);
            }

            // Upsert atômico prevenindo race condition entre SELECT e INSERT/UPDATE
            $sqlUpsert = "INSERT INTO rate_limits (ip, endpoint, attempts, first_attempt)
                          VALUES (?, ?, 1, ?)
                          ON DUPLICATE KEY UPDATE
                              attempts = IF(? - first_attempt > ?, 1, attempts + 1),
                              first_attempt = IF(? - first_attempt > ?, ?, first_attempt)";
            $stmtUpsert = $pdo->prepare($sqlUpsert);
            $stmtUpsert->execute([$ip, $endpoint, $now, $now, $decaySeconds, $now, $decaySeconds, $now]);

            $stmtBusca = $pdo->prepare("SELECT attempts FROM rate_limits WHERE ip = ? AND endpoint = ? FOR UPDATE");
            $stmtBusca->execute([$ip, $endpoint]);
            $attempts = (int) $stmtBusca->fetchColumn();

            if ($iniciadoAqui) {
                $pdo->commit();
            }

            if ($attempts > $maxAttempts) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Muitas requisições. Aguarde antes de tentar novamente.']);
                exit;
            }
        } catch (Throwable $e) {
            if (isset($pdo) && isset($iniciadoAqui) && $iniciadoAqui && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro no rate limiting: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Serviço temporariamente indisponível.']);
            exit;
        }
    }
}

Security::validateActiveSession();
?>
