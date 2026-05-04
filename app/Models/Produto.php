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


    public function salvar($nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem) {
        $sql = "INSERT INTO produtos (nome, categoria, preco, unidade, estoque_atual, peso_estimado_g, imagem_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem]);
    }
    
    public function atualizar($id, $nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem = null) {
        if ($caminhoImagem) {
            $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ?, imagem_url = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem, $id]);
        } else {
            // Se não enviou foto, atualizamos apenas os textos e mantendo a foto antiga
            $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $id]);
        }
    }
}
?>