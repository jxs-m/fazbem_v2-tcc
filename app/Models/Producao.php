<?php
// Caminho: app/Models/Producao.php

require_once __DIR__ . '/../Database.php';

class Producao {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    
    public function obterContagemKitsSemana() {
      
        $sqlAssinaturas = "SELECT COUNT(*) as total FROM assinaturas WHERE status = 'Ativa'";
        $stmtAssinaturas = $this->pdo->query($sqlAssinaturas);
        $totalAssinaturas = $stmtAssinaturas->fetch()['total'];

        // Quantidade de pedidos avulsos feitos desde domingo passado
        $sqlAvulsos = "SELECT COUNT(*) as total FROM pedidos 
                       WHERE tipo_pedido = 'Avulso' 
                       AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)";
        $stmtAvulsos = $this->pdo->query($sqlAvulsos);
        $totalAvulsos = $stmtAvulsos->fetch()['total'];

        return $totalAssinaturas + $totalAvulsos;
    }

    public function gerarRelatorioHortalicas() {
        $sql = "SELECT p.id, p.nome, p.unidade, IFNULL(SUM(ip.quantidade), 0) as total_necessario
                FROM produtos p
                LEFT JOIN itens_pedido ip ON p.id = ip.produto_id
                LEFT JOIN pedidos ped ON ip.pedido_id = ped.id AND YEARWEEK(ped.data_pedido, 0) = YEARWEEK(NOW(), 0)
                GROUP BY p.id, p.nome, p.unidade
                ORDER BY p.nome ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function catalogoAberto() {
        date_default_timezone_set('America/Sao_Paulo');
        $diaSemana = date('w'); // 0 = Domingo, 1 = Segunda, 2 = Terça
        $hora = date('H');
        
        $aberto = false;
        if ($diaSemana == 0 && $hora >= 18) { // Domingo à noite
            $aberto = true;
        } elseif ($diaSemana == 1) { // Segunda o dia todo
            $aberto = true;
        } elseif ($diaSemana == 2 && $hora < 12) { // Terça até meio dia
            $aberto = true;
        }

        if ($this->obterContagemKitsSemana() >= 200) {
            $aberto = false;
        }

        return $aberto;
    }
}
?>
