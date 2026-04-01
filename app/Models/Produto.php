<?php
// Caminho: app/Models/Produto.php


require_once __DIR__ . '/../Database.php';

class Produto {
    private $pdo;

    public function __construct() {
       
        $this->pdo = Database::getConexao();
    }


    public function listarTodos() {
        $sql = "SELECT * FROM produtos ORDER BY nome ASC";
        $stmt = $this->pdo->query($sql);
        
        return $stmt->fetchAll();
    }


    public function salvar($nome, $categoria, $preco, $unidade, $estoque, $caminhoImagem) {
        $sql = "INSERT INTO produtos (nome, categoria, preco, unidade, estoque_atual, imagem_url) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $caminhoImagem]);
    }
}
?>