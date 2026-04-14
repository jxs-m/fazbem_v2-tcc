<?php
// Caminho: app/Models/Assinatura.php

require_once __DIR__ . '/../Database.php';

class Assinatura {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    public function buscarPorUsuario($usuario_id) {
        $sql = "SELECT * FROM assinaturas WHERE usuario_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

    public function atualizar($usuario_id, $frequencia, $status) {
        $assinaturaExistente = $this->buscarPorUsuario($usuario_id);

        if ($assinaturaExistente) {
            $sql = "UPDATE assinaturas SET frequencia = ?, status = ? WHERE usuario_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$frequencia, $status, $usuario_id]);
        } else {
            $sql = "INSERT INTO assinaturas (usuario_id, frequencia, status) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$usuario_id, $frequencia, $status]);
        }
    }
}
?>