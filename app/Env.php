<?php


class Env {
    private static $loadedPath = null;

    /**
     * Carrega variáveis de ambiente exclusivamente do caminho explícito configurado.
     * O fallback para .env.php só ocorre quando explicitamente habilitado e o arquivo principal não existir.
     *
     * @param string $path Caminho do arquivo .env
     * @param bool $allowPhpFallback Se true, permite fallback explícito para $path . '.php'
     * @return bool
     */
    public static function load($path, $allowPhpFallback = false) {
        $explicitEnvFile = getenv('ENV_FILE');
        if (!empty($explicitEnvFile) && is_string($explicitEnvFile)) {
            $path = $explicitEnvFile;
        }

        if (!file_exists($path)) {
            $allowFallbackEnv = getenv('ENV_ALLOW_PHP_FALLBACK') === '1';
            $phpPath = $path . '.php';
            if (($allowPhpFallback || $allowFallbackEnv) && file_exists($phpPath)) {
                $path = $phpPath;
            } else {
                return false;
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '<?php') !== false || strpos($trimmed, '?>') !== false) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    @putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }

        self::$loadedPath = $path;
        return true;
    }

    /**
     * Retorna o valor de uma variável de ambiente carregada.
     * @param string $name Nome da variável
     * @param mixed $default Valor padrão caso não exista
     * @return mixed
     */
    public static function get($name, $default = null) {
        return $_ENV[$name] ?? (getenv($name) !== false ? getenv($name) : $default);
    }

    /**
     * Retorna o ambiente ativo normalizado ('local', 'test' ou 'producao').
     * @return string
     */
    public static function getAmbiente() {
        $amb = strtolower(trim((string) self::get('AMBIENTE', 'producao')));
        return in_array($amb, ['local', 'test', 'producao'], true) ? $amb : 'producao';
    }

    /**
     * Valida se as variáveis obrigatórias estão definidas e não vazias.
     * Registra apenas o nome do ambiente e chaves ausentes, nunca os segredos.
     *
     * @param array $requiredKeys Lista de nomes de variáveis obrigatórias
     * @return array Lista de variáveis ausentes ou inválidas
     */
    public static function validateRequired(array $requiredKeys) {
        $missing = [];
        $placeholders = [
            'seu_usuario',
            'sua_senha',
            'TEST-sua_public_key_aqui',
            'TEST-seu_access_token_aqui'
        ];

        foreach ($requiredKeys as $key) {
            $val = self::get($key);
            if ($val === null || trim((string) $val) === '' || in_array(trim((string) $val), $placeholders, true)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $ambiente = self::getAmbiente();
            error_log("Configuração de ambiente [{$ambiente}] incompleta. Variáveis ausentes/inválidas: " . implode(', ', $missing));
        }

        return $missing;
    }
}
?>
