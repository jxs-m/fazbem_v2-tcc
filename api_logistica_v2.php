<?php
// Caminho: faz_bem_v2/api_logistica_v2.php
header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';

try {
    $pdo = Database::getConexao();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
       
        $sql = "SELECT p.id as pedido_id, p.valor_total, p.status_pagamento, p.status_entrega,
                       u.nome, u.telefone, 
                       COALESCE(e.logradouro, u.endereco) as logradouro, 
                       COALESCE(e.ponto_referencia, u.ponto_referencia) as ponto_referencia, 
                       e.latitude, e.longitude
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN enderecos e ON e.usuario_id = u.id
                WHERE p.status_entrega IN ('Em separação', 'Saiu para entrega')
                GROUP BY p.id
                ORDER BY p.id ASC";
                
        $stmt = $pdo->query($sql);
        $entregas = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $entregas
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Atualiza status da entrega
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['pedido_id']) || !isset($input['status'])) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $sql = "UPDATE pedidos SET status_entrega = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$input['status'], $input['pedido_id']]);

        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de sistema: ' . $e->getMessage()]);
}
?>
