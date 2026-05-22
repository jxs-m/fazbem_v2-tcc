<?php
// Caminho: app/Models/Dashboard.php

require_once __DIR__ . '/../Database.php';

class Dashboard {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    public function getTotalPedidos() {
        $sql = "SELECT COUNT(id) as total FROM pedidos";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['total'];
    }

    public function getFaturamentoTotal() {
        $sql = "SELECT SUM(valor_total) as faturamento FROM pedidos WHERE status_pagamento != 'Cancelado'";
        $stmt = $this->pdo->query($sql);
        $resultado = $stmt->fetch()['faturamento'];
        return $resultado ? $resultado : 0; // Se for null, retorna 0
    }

    public function getEstoqueCritico() {
        $sql = "SELECT COUNT(id) as critico FROM produtos WHERE estoque_atual < 10";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['critico'];
    }

    public function getTotalClientes() {
        $sql = "SELECT COUNT(id) as clientes FROM usuarios WHERE tipo_usuario = 'cliente'";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['clientes'];
    }
    
    public function getTotalCreditos() {
        $sql = "SELECT SUM(u.saldo_compensacao) as total_creditos 
                FROM usuarios u
                JOIN assinaturas a ON u.id = a.usuario_id
                WHERE u.tipo_usuario = 'cliente' 
                AND a.status = 'Pausada'";
        $stmt = $this->pdo->query($sql);
        $resultado = $stmt->fetch()['total_creditos'];
        return $resultado ? $resultado : 0;
    }

    public function getAssinantesInativos() {
        $sql = "SELECT u.nome, u.telefone, a.status 
                FROM usuarios u 
                JOIN assinaturas a ON u.id = a.usuario_id 
                WHERE a.status IN ('Pausada', 'Cancelada')
                ORDER BY a.atualizado_em DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getResumoGeral() {
        return [
            'total_pedidos' => $this->getTotalPedidos(),
            'faturamento' => $this->getFaturamentoTotal(),
            'estoque_critico' => $this->getEstoqueCritico(),
            'total_clientes' => $this->getTotalClientes(),
            'total_creditos' => $this->getTotalCreditos(),
            'assinantes_inativos' => $this->getAssinantesInativos()
        ];
    }
}
?>