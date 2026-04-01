<?php
// Caminho: app/Models/Dashboard.php

require_once __DIR__ . '/../Database.php';

class Dashboard {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    // 1. Conta o total de pedidos realizados
    public function getTotalPedidos() {
        $sql = "SELECT COUNT(id) as total FROM pedidos";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['total'];
    }

    // 2. Soma o faturamento (apenas pedidos não cancelados)
    public function getFaturamentoTotal() {
        $sql = "SELECT SUM(valor_total) as faturamento FROM pedidos WHERE status_pagamento != 'Cancelado'";
        $stmt = $this->pdo->query($sql);
        $resultado = $stmt->fetch()['faturamento'];
        return $resultado ? $resultado : 0; // Se for null, retorna 0
    }

    // 3. Conta quantos produtos estão com estoque menor que 10
    public function getEstoqueCritico() {
        $sql = "SELECT COUNT(id) as critico FROM produtos WHERE estoque_atual < 10";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['critico'];
    }

    // 4. Conta quantos clientes existem no sistema
    public function getTotalClientes() {
        $sql = "SELECT COUNT(id) as clientes FROM usuarios WHERE tipo_usuario = 'cliente'";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['clientes'];
    }
    
    // 5. Agrupa todas as métricas num único array para facilitar
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