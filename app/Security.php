<?php
// Caminho: faz_bem_v2/app/Security.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

        $headers = getallheaders();
        $tokenEnviado = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';

        if (empty($tokenEnviado) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de segurança CSRF inválido ou ausente.']);
            exit;
        }
    }

    /**
     
     * @param int $maxAttempts Quantidade máxima de requisições permitidas
     * @param int $decaySeconds Tempo em segundos para limpar o registro
     */
    public static function checkRateLimit($maxAttempts = 4, $decaySeconds = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $endpoint = $_SERVER['SCRIPT_NAME'];
        
        require_once __DIR__ . '/Database.php';
        $pdo = Database::getConexao();

        $now = time();

        // Busca o registro atual
        $sqlBusca = "SELECT attempts, first_attempt FROM rate_limits WHERE ip = ? AND endpoint = ?";
        $stmtBusca = $pdo->prepare($sqlBusca);
        $stmtBusca->execute([$ip, $endpoint]);
        $row = $stmtBusca->fetch();

        if (!$row) {
            // Insere o primeiro acesso
            $sqlInsert = "INSERT INTO rate_limits (ip, endpoint, attempts, first_attempt) VALUES (?, ?, 1, ?)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([$ip, $endpoint, $now]);
        } else {
            // Se o tempo expirou, reseta. Se não, incrementa.
            if ($now - $row['first_attempt'] > $decaySeconds) {
                $sqlUpdate = "UPDATE rate_limits SET attempts = 1, first_attempt = ? WHERE ip = ? AND endpoint = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$now, $ip, $endpoint]);
            } else {
                $attempts = $row['attempts'] + 1;
                $sqlUpdate = "UPDATE rate_limits SET attempts = ? WHERE ip = ? AND endpoint = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$attempts, $ip, $endpoint]);

                if ($attempts > $maxAttempts) {
                    http_response_code(429);
                    echo json_encode(['success' => false, 'message' => 'Muitas requisições. Aguarde antes de tentar novamente.']);
                    exit;
                }
            }
        }
    }
}
?>
