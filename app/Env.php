<?php


class Env {
    public static function load($path) {
        if (!file_exists($path)) {
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
            
            if (strpos(trim($line), '#') === 0) {
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
}
?>
