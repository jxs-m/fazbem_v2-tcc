<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_logistica_v2.php

header('Content-Type: application/json');

Security::checkCSRF();
Security::checkRateLimit(60, 60);

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tipo_usuario']) || !in_array($_SESSION['tipo_usuario'], ['admin', 'entregador'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Pedido.php';

try {
    $pdo = Database::getConexao();
    $usuarioId = (int) $_SESSION['usuario_id'];
    $papel = (string) $_SESSION['tipo_usuario'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($papel === 'entregador') {
            // Entregador vê exclusivamente pedidos da própria rota ativa, data de hoje e atribuição
            $sql = "SELECT p.id as pedido_id, p.valor_total, p.status_pagamento, p.status_entrega, p.ordem_entrega,
                           p.rota_id, p.data_entrega, r.data_rota, r.status as rota_status,
                           u.nome, u.telefone, 
                           COALESCE(e.logradouro, u.endereco) as logradouro, 
                           COALESCE(e.ponto_referencia, u.ponto_referencia) as ponto_referencia, 
                           e.latitude, e.longitude
                    FROM pedidos p
                    INNER JOIN rotas r ON p.rota_id = r.id
                    INNER JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN (
                        SELECT usuario_id, MIN(id) as principal_id
                        FROM enderecos
                        WHERE is_principal = 1
                        GROUP BY usuario_id
                    ) e_id ON e_id.usuario_id = u.id
                    LEFT JOIN enderecos e ON e.id = e_id.principal_id
                    WHERE p.status_entrega IN ('Aguardando Entrega', 'Saiu para entrega')
                      AND p.status_pagamento != 'Cancelado'
                      AND r.entregador_id = ?
                      AND r.data_rota = CURDATE()
                      AND r.status IN ('Pendente', 'Rota Iniciada')
                      AND (p.data_entrega IS NULL OR p.data_entrega = CURDATE())
                    ORDER BY p.ordem_entrega ASC, p.id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuarioId]);
            $entregas = $stmt->fetchAll();
        } else {
            // Admin visualiza todas as entregas operacionais ativas
            $sql = "SELECT p.id as pedido_id, p.valor_total, p.status_pagamento, p.status_entrega, p.ordem_entrega,
                           p.rota_id, p.data_entrega, r.entregador_id, r.data_rota, r.status as rota_status,
                           u.nome, u.telefone, 
                           COALESCE(e.logradouro, u.endereco) as logradouro, 
                           COALESCE(e.ponto_referencia, u.ponto_referencia) as ponto_referencia, 
                           e.latitude, e.longitude
                    FROM pedidos p
                    LEFT JOIN rotas r ON p.rota_id = r.id
                    INNER JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN (
                        SELECT usuario_id, MIN(id) as principal_id
                        FROM enderecos
                        WHERE is_principal = 1
                        GROUP BY usuario_id
                    ) e_id ON e_id.usuario_id = u.id
                    LEFT JOIN enderecos e ON e.id = e_id.principal_id
                    WHERE p.status_entrega IN ('Aguardando Entrega', 'Saiu para entrega')
                      AND p.status_pagamento != 'Cancelado'
                    ORDER BY p.ordem_entrega ASC, p.id ASC";
            $stmt = $pdo->query($sql);
            $entregas = $stmt->fetchAll();
        }

        echo json_encode([
            'success' => true,
            'data' => $entregas
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $pedidoId = filter_var($input['pedido_id'] ?? null, FILTER_VALIDATE_INT);
        $status = isset($input['status']) ? trim((string) $input['status']) : '';
        $motivo = isset($input['motivo']) ? trim((string) $input['motivo']) : null;

        if (!$pedidoId || $status === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $pedidoModel = new Pedido();
        $pedidoModel->atualizarStatus($pedidoId, 'entrega', $status, $usuarioId, $papel, $motivo);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    error_log("DB Error na logística: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
} catch (Exception $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 403, 404], true)) {
        $code = 400;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
