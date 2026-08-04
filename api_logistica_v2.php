<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_logistica_v2.php

header('Content-Type: application/json');

Security::checkCSRF();

if (!isset($_SESSION['tipo_usuario']) || !in_array($_SESSION['tipo_usuario'], ['admin', 'entregador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

require_once __DIR__ . '/app/Database.php';

try {
    $pdo = Database::getConexao();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
       
        $sql = "SELECT p.id as pedido_id, p.valor_total, p.status_pagamento, p.status_entrega, p.ordem_entrega,
                       u.nome, u.telefone, 
                       COALESCE(e.logradouro, u.endereco) as logradouro, 
                       COALESCE(e.ponto_referencia, u.ponto_referencia) as ponto_referencia, 
                       e.latitude, e.longitude
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN (
                    SELECT usuario_id, MIN(id) as principal_id
                    FROM enderecos
                    WHERE is_principal = 1
                    GROUP BY usuario_id
                ) e_id ON e_id.usuario_id = u.id
                LEFT JOIN enderecos e ON e.id = e_id.principal_id
                WHERE p.status_entrega IN ('Aguardando Entrega', 'Saiu para entrega')
                ORDER BY p.ordem_entrega ASC, p.id ASC";
                
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

        $status = $input['status'];
        $allowedStatuses = ['Em separação', 'Aguardando Entrega', 'Saiu para entrega', 'Entregue'];
        
        if (!in_array($status, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Status inválido']);
            exit;
        }

        if ($status === 'Entregue') {
            $sql = "UPDATE pedidos SET status_entrega = ?, entregue_em = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE pedidos SET status_entrega = ? WHERE id = ?";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $input['pedido_id']]);

        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    error_log("Erro na logística: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>
