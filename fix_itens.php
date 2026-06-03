<?php
require_once __DIR__ . '/app/Database.php';
$pdo = Database::getConexao();

// Find all orders from this week
$sql = "SELECT id FROM pedidos WHERE YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0) AND tipo_pedido = 'Assinatura'";
$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($pedidos)) {
    echo "Nenhum pedido da semana encontrado.\n";
    exit;
}

// Find items in these orders that have quantity > 1, and the product is 'Inteiro' and stock unit is 'kg'
$sqlItens = "SELECT i.id, i.pedido_id, i.produto_id, i.quantidade, p.unidade, p.tipo_venda, p.peso_estimado_g, p.nome 
             FROM itens_pedido i
             JOIN produtos p ON i.produto_id = p.id
             WHERE i.pedido_id IN (" . implode(',', $pedidos) . ")";
$stmtItens = $pdo->query($sqlItens);
$itens = $stmtItens->fetchAll();

foreach ($itens as $item) {
    // If it's a product like Apple that should have been converted to kg
    if ($item['tipo_venda'] === 'Inteiro' && strtolower($item['unidade']) === 'kg' && $item['quantidade'] >= 1) {
        $real_qty = ($item['quantidade'] * $item['peso_estimado_g']) / 1000;
        
        echo "Corrigindo item {$item['id']} ({$item['nome']}) no pedido {$item['pedido_id']}: {$item['quantidade']} -> $real_qty\n";
        
        $upd = $pdo->prepare("UPDATE itens_pedido SET quantidade = ? WHERE id = ?");
        $upd->execute([$real_qty, $item['id']]);
    }
}
echo "Correção finalizada.\n";
