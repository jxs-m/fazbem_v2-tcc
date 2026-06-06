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


    public function salvar($nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem, $tipo_venda, $temporario = 0, $duracao_dias = null) {
        $sql = "INSERT INTO produtos (nome, categoria, preco, unidade, estoque_atual, peso_estimado_g, imagem_url, tipo_venda, temporario, duracao_dias) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem, $tipo_venda, $temporario, $duracao_dias]);
    }
    
    public function atualizar($id, $nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem = null, $tipo_venda = 'Inteiro', $temporario = 0, $duracao_dias = null) {
        if ($caminhoImagem) {
            $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ?, imagem_url = ?, tipo_venda = ?, temporario = ?, duracao_dias = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem, $tipo_venda, $temporario, $duracao_dias, $id]);
        } else {
            // Se não enviou foto, atualizamos apenas os textos e mantendo a foto antiga
            $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ?, tipo_venda = ?, temporario = ?, duracao_dias = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $tipo_venda, $temporario, $duracao_dias, $id]);
        }
    }

    public function deletar($id) {
        $sql = "DELETE FROM produtos WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>