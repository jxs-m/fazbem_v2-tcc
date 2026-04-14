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
    
    public function getResumoGeral() {
        return [
            'total_pedidos' => $this->getTotalPedidos(),
            'faturamento' => $this->getFaturamentoTotal(),
            'estoque_critico' => $this->getEstoqueCritico(),
            'total_clientes' => $this->getTotalClientes()
        ];
    }
}
?>