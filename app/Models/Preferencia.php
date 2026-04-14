<?php
// Caminho: app/Models/Preferencia.php

require_once __DIR__ . '/../Database.php';

class Preferencia {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    public function adicionar($usuario_id, $tipo, $descricao) {
        $sql = "INSERT INTO preferencias (usuario_id, tipo, descricao) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$usuario_id, $tipo, $descricao]);
    }

    public function buscarPorUsuario($usuario_id) {
        $sql = "SELECT id, tipo, descricao FROM preferencias WHERE usuario_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    public function remover($id, $usuario_id) {
        $sql = "DELETE FROM preferencias WHERE id = ? AND usuario_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id, $usuario_id]);
    }
}
?>
