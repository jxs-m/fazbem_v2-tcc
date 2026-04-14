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

    /**
     * Valida o token CSRF vindo no cabeçalho X-CSRF-Token da requisição.
     * Interrompe a execução com erro 403 se o token for inválido.
     */
    public static function checkCSRF() {
        // Se a requisição for GET, OPTIONS, HEAD, não precisa validar
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
     * Rate Limiting simples usando um arquivo JSON baseado no IP e a URL consumida.
     * @param int $maxAttempts Quantidade máxima de requisições permitidas
     * @param int $decaySeconds Tempo em segundos para limpar o registro
     */
    public static function checkRateLimit($maxAttempts = 5, $decaySeconds = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $endpoint = $_SERVER['SCRIPT_NAME'];
        $cacheFile = __DIR__ . '/rate_limit.json';

        // Carrega arquivo de cache ou cria array vazio
        $data = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
        if (!is_array($data)) $data = [];

        $key = $ip . '_' . $endpoint;
        $now = time();

        // Inicializa se não existir
        if (!isset($data[$key])) {
            $data[$key] = ['attempts' => 1, 'first_attempt' => $now];
        } else {
            // Verifica se o tempo de bloqueio já expirou, se sim, reseta
            if ($now - $data[$key]['first_attempt'] > $decaySeconds) {
                $data[$key] = ['attempts' => 1, 'first_attempt' => $now];
            } else {
                $data[$key]['attempts']++;
            }
        }

        // Bloqueia com erro HTTP 429 se excedeu
        if ($data[$key]['attempts'] > $maxAttempts) {
            file_put_contents($cacheFile, json_encode($data));
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Muitas requisições. Aguarde antes de tentar novamente.']);
            exit;
        }

        // Salva arquivo
        file_put_contents($cacheFile, json_encode($data));
    }
}
?>
