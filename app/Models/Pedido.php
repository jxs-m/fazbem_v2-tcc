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
        $sql = "SELECT i.quantidade, i.preco_unitario, pr.nome, pr.unidade 
                FROM itens_pedido i
                JOIN produtos pr ON i.produto_id = pr.id
                WHERE i.pedido_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pedido_id]);
        return $stmt->fetchAll();
    }

   
    public function atualizarStatus($id, $tipo, $valor) {
        // Define qual coluna do banco será atualizada dependendo do que a tela enviou
        $coluna = ($tipo === 'pagamento') ? 'status_pagamento' : 'status_entrega';
        
        $sql = "UPDATE pedidos SET $coluna = ? WHERE id = ?";
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

    public function criarPedido($usuario_id, $valor_total, $forma_pagamento, $carrinho) {
        try {
            // Inicia a transação: ou salva tudo (pedido + itens + baixa de estoque), ou não salva nada.
            $this->pdo->beginTransaction();

          
    $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega) 
                  VALUES (?, ?, 'Pendente', 'Em separação')";
            
            $stmtPedido = $this->pdo->prepare($sqlPedido);
            $stmtPedido->execute([$usuario_id, $valor_total]);
            
            // Pega o número (ID) do pedido que acabou de ser gerado
            $pedido_id = $this->pdo->lastInsertId();

            // 2. Prepara os comandos para os itens e para o estoque
            $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) 
                        VALUES (?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            $sqlEstoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
            $stmtEstoque = $this->pdo->prepare($sqlEstoque);

            // 3. Roda um loop em cada produto do carrinho do cliente
            foreach ($carrinho as $item) {
                // Salva o item vinculado ao número do pedido
                $stmtItem->execute([$pedido_id, $item['id'], $item['quantidade'], $item['preco']]);
                
                // Desconta a quantidade comprada do estoque atual do produto
                $stmtEstoque->execute([$item['quantidade'], $item['id']]);
            }

            // Se o código chegou até aqui sem erros, confirma a gravação de tudo!
            $this->pdo->commit();
            
            return $pedido_id; // Retorna o número do pedido para a tela de sucesso

        } catch (Exception $e) {
            // Deu algum erro (ex: acabou a luz do servidor)? Desfaz tudo!
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>