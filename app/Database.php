<?php
// Caminho: app/Database.php

require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/../.env');

class Database {
    private static $conexao;

    private function __construct() {}

    /**
     * Permite injetar ou resetar a conexão (útil para testes automatizados em banco isolado).
     */
    public static function setConexao(?PDO $pdo = null) {
        self::$conexao = $pdo;
    }

    public static function getConexao() {
        if (!isset(self::$conexao)) {
            try {
                if (file_exists(__DIR__ . '/../config.php')) {
                    require_once __DIR__ . '/../config.php';
                }

                if (isset($pdo) && $pdo instanceof PDO) {
                    self::$conexao = $pdo;
                } else {
                    $host = defined('DB_HOST') ? DB_HOST : Env::get('DB_HOST', 'localhost');
                    $dbname = defined('DB_NAME') ? DB_NAME : Env::get('DB_NAME', 'fazbem_v2');
                    $user = defined('DB_USER') ? DB_USER : Env::get('DB_USER', 'root');
                    $pass = defined('DB_PASS') ? DB_PASS : (Env::get('DB_PASS') !== null ? Env::get('DB_PASS') : '');
                    $port = defined('DB_PORT') ? DB_PORT : Env::get('DB_PORT', '');
                    $socket = defined('DB_SOCKET') ? DB_SOCKET : Env::get('DB_SOCKET', '');

                    if (!empty($socket)) {
                        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
                    } else {
                        $portPart = (!empty($port)) ? ";port=$port" : "";
                        $dsn = "mysql:host=$host$portPart;dbname=$dbname;charset=utf8mb4";
                    }

                    self::$conexao = new PDO(
                        $dsn,
                        $user,
                        $pass,
                        [PDO::ATTR_EMULATE_PREPARES => false]
                    );
                }
                self::$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $ambiente = defined('AMBIENTE') ? AMBIENTE : Env::getAmbiente();
                if (in_array($ambiente, ['local', 'test'], true)) {
                    die("Erro de conexão com o banco de dados: " . $e->getMessage());
                } else {
                    die("Erro temporário de conexão. Por favor, tente mais tarde.");
                }
            }
        }
        return self::$conexao;
    }
}
?>