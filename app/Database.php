<?php
// Caminho: app/Database.php

class Database {
    private static $conexao;

    private function __construct() {}

    public static function getConexao() {
        if (!isset(self::$conexao)) {
            try {
                $host = 'localhost';
                
                $dbname = 'fazbem_v2';
                $user = 'root';
                $pass = ''; 

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