<?php
// Caminho: app/Database.php

require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/../.env');

class Database {
    private static $conexao;

    private function __construct() {}

    public static function getConexao() {
        if (!isset(self::$conexao)) {
            try {
                $host = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
                $dbname = $_ENV['DB_NAME'] ?? (getenv('DB_NAME') ?: 'fazbem_v2');
                $user = $_ENV['DB_USER'] ?? (getenv('DB_USER') ?: 'root');
                $pass = $_ENV['DB_PASS'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''); 

                self::$conexao = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
                self::$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                die("Erro de conexão com o banco de dados: " . $e->getMessage());
            }
        }
        return self::$conexao;
    }
}
?>