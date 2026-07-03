<?php
// Caminho: app/Models/Producao.php

require_once __DIR__ . '/../Database.php';

class Producao {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    
    public function obterContagemKitsSemana() {
        // Conta usuários únicos que possuem assinatura Ativa OU que fizeram algum pedido na semana atual
        $sql = "SELECT COUNT(DISTINCT u.id) as total 
                FROM usuarios u
                LEFT JOIN assinaturas a ON u.id = a.usuario_id AND a.status = 'Ativa'
                LEFT JOIN pedidos p ON u.id = p.usuario_id AND YEARWEEK(p.data_pedido, 0) = YEARWEEK(NOW(), 0)
                WHERE a.id IS NOT NULL OR p.id IS NOT NULL";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch()['total'];
    }

    public function gerarRelatorioHortalicas() {
        $sql = "SELECT p.id, p.nome, p.unidade, p.tipo_venda, IFNULL(SUM(ip.quantidade), 0) as total_necessario
                FROM produtos p
                LEFT JOIN itens_pedido ip ON p.id = ip.produto_id
                LEFT JOIN pedidos ped ON ip.pedido_id = ped.id AND YEARWEEK(ped.data_pedido, 0) = YEARWEEK(NOW(), 0) AND ped.status_entrega IN ('Em separação', 'Aguardando Entrega')
                GROUP BY p.id, p.nome, p.unidade, p.tipo_venda
                HAVING total_necessario > 0
                ORDER BY p.nome ASC";
        $stmt = $this->pdo->query($sql);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as &$item) {
            $item['quantidade_formatada'] = self::formatarExibicao($item['nome'], $item['unidade'], $item['tipo_venda'], $item['total_necessario']);
        }
        return $itens;
    }

    public static function formatarExibicao($nome, $unidade, $tipoVenda, $quantidade) {
        $qtd = floatval($quantidade);
        if ($qtd <= 0) return '0';

        $u = strtolower(trim($unidade));
        $n = strtolower(trim($nome));

        if ($tipoVenda === 'Fracionado' || $u === 'kg' || $u === 'g') {
            if ($u === 'g') {
                return round($qtd) . ' g';
            }
            $formatted = number_format($qtd, 3, ',', '.');
            $formatted = preg_replace('/,000$/', '', $formatted);
            $formatted = preg_replace('/,([0-9]*[1-9])0+$/', ',$1', $formatted);
            return $formatted . ' kg';
        } else {
            $numInt = (int)round($qtd);
            $unitStr = '';

            if (empty($u) || $u === 'un' || $u === 'unidade') {
                $unitStr = ($numInt === 1) ? 'unidade' : 'unidades';
            } elseif ($u === 'maço' || $u === 'maco') {
                $unitStr = ($numInt === 1) ? 'maço' : 'maços';
            } elseif ($u === 'pé' || $u === 'pe') {
                $unitStr = ($numInt === 1) ? 'pé' : 'pés';
            } elseif ($u === $n) {
                if ($numInt === 1) {
                    $unitStr = $nome;
                } else {
                    $ultLetra = mb_substr($n, -1);
                    if (in_array($ultLetra, ['a', 'e', 'i', 'o', 'u'])) {
                        $unitStr = $nome . 's';
                    } else {
                        $unitStr = $nome . 'es';
                    }
                }
            } else {
                if ($numInt === 1) {
                    $unitStr = $unidade;
                } else {
                    $ultLetra = mb_substr($u, -1);
                    if (in_array($ultLetra, ['a', 'e', 'i', 'o', 'u'])) {
                        $unitStr = $unidade . 's';
                    } else {
                        $unitStr = $unidade . 'es';
                    }
                }
            }
            return $numInt . ' ' . $unitStr;
        }
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
