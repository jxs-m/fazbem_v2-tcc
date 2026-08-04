<?php


class Env {
    public static function load($path) {
        $phpPath = $path . '.php';
        if (file_exists($phpPath)) {
            $path = $phpPath;
        } elseif (!file_exists($path)) {
            $dir = dirname($path);
            $filename = basename($path);
            $alternative = (strtolower($filename) === '.env') ? '.ENV' : '.env';
            $altPath = $dir . DIRECTORY_SEPARATOR . $alternative;
            if (file_exists($altPath)) {
                $path = $altPath;
            } else {
                return false;
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            
            if (strpos(trim($line), '#') === 0 || strpos(trim($line), '<?php') !== false || strpos(trim($line), '?>') !== false) {
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
}
?>
