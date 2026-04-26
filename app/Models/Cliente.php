<?php
// Caminho: app/Models/Cliente.php

require_once __DIR__ . '/../Database.php';

class Cliente {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    public function listarTodos() {
        $sql = "SELECT u.id, u.nome, u.email, u.telefone, u.endereco, 
                       a.frequencia, a.status,
                       e.latitude, e.longitude,
                       COALESCE((SELECT SUM(valor_total) FROM pedidos WHERE usuario_id = u.id AND status_pagamento != 'Cancelado'), 0) as total_gasto,
                       (SELECT GROUP_CONCAT(descricao SEPARATOR '; ') FROM preferencias WHERE usuario_id = u.id) as preferencias
                FROM usuarios u
                LEFT JOIN assinaturas a ON u.id = a.usuario_id
                LEFT JOIN enderecos e ON u.id = e.usuario_id
                WHERE u.tipo_usuario = 'cliente'
                ORDER BY u.nome ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function atualizar($id, $nome, $telefone, $endereco, $frequencia, $status, $latitude = null, $longitude = null) {
        try {
            $this->pdo->beginTransaction();

            $sqlUser = "UPDATE usuarios SET nome = ?, telefone = ?, endereco = ? WHERE id = ?";
            $this->pdo->prepare($sqlUser)->execute([$nome, $telefone, $endereco, $id]);

            $check = $this->pdo->prepare("SELECT id FROM assinaturas WHERE usuario_id = ?");
            $check->execute([$id]);
            
            if ($check->rowCount() > 0) {
                $sqlAss = "UPDATE assinaturas SET frequencia = ?, status = ? WHERE usuario_id = ?";
                $this->pdo->prepare($sqlAss)->execute([$frequencia, $status, $id]);
            } else {
                $sqlAss = "INSERT INTO assinaturas (usuario_id, frequencia, status) VALUES (?, ?, ?)";
                $this->pdo->prepare($sqlAss)->execute([$id, $frequencia, $status]);
            }

            if ($latitude !== null && $longitude !== null) {
                $checkEnd = $this->pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
                $checkEnd->execute([$id]);
                if ($checkEnd->rowCount() > 0) {
                    $sqlEnd = "UPDATE enderecos SET logradouro = ?, latitude = ?, longitude = ? WHERE usuario_id = ?";
                    $this->pdo->prepare($sqlEnd)->execute([$endereco, $latitude, $longitude, $id]);
                } else {
                    $sqlEnd = "INSERT INTO enderecos (usuario_id, logradouro, is_principal, latitude, longitude) VALUES (?, ?, 1, ?, ?)";
                    $this->pdo->prepare($sqlEnd)->execute([$id, $endereco, $latitude, $longitude]);
                }
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>