<?php
// Caminho: app/Models/Pedido.php

require_once __DIR__ . '/../Database.php';

class Pedido {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

   
    public function listarTodos() {
     
        $sql = "SELECT p.id, p.valor_total, p.status_pagamento, p.status_entrega, p.data_pedido, u.nome as cliente
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                ORDER BY p.data_pedido DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

   
    public function buscarDetalhes($id) {
        $sql = "SELECT p.*, u.nome, u.telefone, u.endereco, u.ponto_referencia 
                FROM pedidos p 
                JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

   
    public function buscarItens($pedido_id) {
        $sql = "SELECT i.quantidade, i.quantidade_real, i.preco_unitario, i.preco_real, pr.nome, pr.unidade, pr.tipo_venda, pr.peso_estimado_g 
                FROM itens_pedido i
                JOIN produtos pr ON i.produto_id = pr.id
                WHERE i.pedido_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pedido_id]);
        return $stmt->fetchAll();
    }

    public function buscarPedidoSemana($usuario_id) {
        $sql = "SELECT id, valor_total, status_pagamento, status_entrega, obs_pontual, data_pedido, tipo_pedido 
                FROM pedidos 
                WHERE usuario_id = ? AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

   
    public function atualizarStatus($id, $tipo, $valor) {
        // Define qual coluna do banco será atualizada dependendo do que a tela enviou
        $coluna = ($tipo === 'pagamento') ? 'status_pagamento' : 'status_entrega';
        
        if ($tipo === 'entrega' && $valor === 'Entregue') {
            $sql = "UPDATE pedidos SET status_entrega = ?, entregue_em = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE pedidos SET $coluna = ? WHERE id = ?";
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$valor, $id]);
    }

    public function buscarPorUsuario($usuario_id) {
        $sql = "SELECT id, valor_total, status_pagamento, status_entrega, data_pedido 
                FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    public function verificarPedidoExistenteSemana($usuario_id) {
        $sql = "SELECT COUNT(id) as total FROM pedidos 
                WHERE usuario_id = ? AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch()['total'] > 0;
    }

    public function criarPedido($usuario_id, $valor_total_cliente, $forma_pagamento, $carrinho, $transacao_id = null, $status_pagamento = 'Pendente') {
        try {
            $this->pdo->beginTransaction();

            $total_calculado = 0;
            $itens_processados = [];

            // Validar e recalcular itens e total com dados seguros do banco de dados
            $stmtProd = $this->pdo->prepare("SELECT nome, preco, unidade, peso_estimado_g, tipo_venda FROM produtos WHERE id = ?");
            
            foreach ($carrinho as $item) {
                $produto_id = intval($item['id'] ?? 0);
                $stmtProd->execute([$produto_id]);
                $prodInfo = $stmtProd->fetch();
                
                if (!$prodInfo) {
                    throw new Exception("Produto não encontrado.");
                }
                
                $db_preco_base = floatval($prodInfo['preco']);
                $peso_estimado_g = floatval($prodInfo['peso_estimado_g'] ?? 0);
                $tipo_venda = $prodInfo['tipo_venda'];
                $unidade = strtolower($prodInfo['unidade']);
                
                // Determina baseGrams
                $baseGrams = null;
                if (strpos($unidade, 'kg') !== false) {
                    $baseGrams = 1000;
                } elseif (strpos($unidade, 'g') !== false) {
                    $num = intval($unidade);
                    if ($num > 0) $baseGrams = $num;
                }

                $isNovoModelo = isset($item['preco_estimado_calculado']) || isset($item['tipo_compra']);
                if ($isNovoModelo) {
                    $input_qtd = floatval($item['input_qtd'] ?? 0);
                    if ($input_qtd <= 0) {
                        throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                    }
                    $tipo_compra = $item['tipo_compra'] ?? 'Unidade';
                    
                    if ($tipo_compra === 'Unidade') {
                        $requestedGrams = $input_qtd * $peso_estimado_g;
                        $quantidade_calculada = ($baseGrams !== null) ? ($requestedGrams / $baseGrams) : $input_qtd;
                    } else {
                        $requestedGrams = $input_qtd;
                        $quantidade_calculada = ($baseGrams !== null) ? ($requestedGrams / $baseGrams) : $input_qtd;
                    }
                } else {
                    $quantidade_calculada = floatval($item['quantidade'] ?? 0);
                    if ($quantidade_calculada <= 0) {
                        throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                    }
                    $requestedGrams = $quantidade_calculada * $peso_estimado_g;
                }

                $subtotal = $quantidade_calculada * $db_preco_base;
                $total_calculado += $subtotal;

                $estoqueDecremento = $quantidade_calculada;
                if ($tipo_venda === 'Fracionado') {
                    $estoqueDecremento = $requestedGrams;
                }

                $itens_processados[] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade_calculada,
                    'preco_unitario' => $db_preco_base,
                    'estoque_decremento' => $estoqueDecremento
                ];
            }

            // Inserir o pedido com o valor total recalculado e seguro
            $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, forma_pagamento, transacao_id) 
                          VALUES (?, ?, ?, 'Em separação', ?, ?)";
            $stmtPedido = $this->pdo->prepare($sqlPedido);
            $stmtPedido->execute([$usuario_id, $total_calculado, $status_pagamento, $forma_pagamento, $transacao_id]);
            
            $pedido_id = $this->pdo->lastInsertId();

            // Inserir itens do pedido e atualizar estoque
            $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) 
                        VALUES (?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            $sqlEstoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
            $stmtEstoque = $this->pdo->prepare($sqlEstoque);

            foreach ($itens_processados as $ip) {
                $stmtItem->execute([$pedido_id, $ip['produto_id'], $ip['quantidade'], $ip['preco_unitario']]);
                $stmtEstoque->execute([$ip['estoque_decremento'], $ip['produto_id']]);
            }

            $this->pdo->commit();
            return $pedido_id;

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>