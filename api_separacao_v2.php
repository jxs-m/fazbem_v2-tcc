<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_separacao_v2.php

header('Content-Type: application/json');

Security::checkCSRF();
Security::checkRateLimit(60, 60);

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Pedido.php';

// Apenas admin ou separador autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tipo_usuario']) || !in_array($_SESSION['tipo_usuario'], ['admin', 'separador'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = Database::getConexao();
$usuarioId = (int) $_SESSION['usuario_id'];
$papel = (string) $_SESSION['tipo_usuario'];

try {
    if ($method === 'GET') {
        // Listar apenas pedidos autorizados para a operação de separação (não cancelados e dentro do ciclo operacional)
        $sql = "SELECT p.id, p.data_pedido, p.obs_pontual, u.nome as cliente 
                FROM pedidos p 
                JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.status_entrega = 'Em separação' 
                  AND p.status_pagamento != 'Cancelado'
                  AND p.fatura_id IS NULL
                  AND (
                      (p.data_entrega IS NOT NULL AND p.data_entrega >= CURDATE())
                      OR (p.data_entrega IS NULL AND YEARWEEK(p.data_pedido, 0) = YEARWEEK(NOW(), 0))
                  )
                ORDER BY p.id ASC";
        $pedidos = $pdo->query($sql)->fetchAll();

        foreach ($pedidos as &$ped) {
            // Buscar itens preservando snapshot
            $sqlItens = "SELECT i.id as item_id, i.quantidade, i.preco_unitario,
                                COALESCE(i.produto_nome, pr.nome) as nome,
                                COALESCE(i.produto_unidade, pr.unidade) as unidade,
                                pr.tipo_venda, pr.peso_estimado_g 
                         FROM itens_pedido i 
                         JOIN produtos pr ON i.produto_id = pr.id 
                         WHERE i.pedido_id = ?";
            $stmt = $pdo->prepare($sqlItens);
            $stmt->execute([$ped['id']]);
            $ped['itens'] = $stmt->fetchAll();

            $sqlPrefs = "SELECT tipo, descricao FROM preferencias WHERE usuario_id = (SELECT usuario_id FROM pedidos WHERE id = ?)";
            $stmtPrefs = $pdo->prepare($sqlPrefs);
            $stmtPrefs->execute([$ped['id']]);
            $ped['preferencias'] = $stmtPrefs->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'pedidos' => $pedidos]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $pedido_id = filter_var($data['pedido_id'] ?? null, FILTER_VALIDATE_INT);
        $itensPesados = is_array($data['itens'] ?? null) ? $data['itens'] : [];
        $motivo = isset($data['motivo']) ? trim((string) $data['motivo']) : null;

        if (!$pedido_id || empty($itensPesados)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            exit;
        }

        $pedidoModel = new Pedido();
        $pedidoModel->registrarPesagemSeparacao($pedido_id, $itensPesados, $usuarioId, $papel, $motivo);

        echo json_encode(['success' => true, 'message' => 'Pesagem registrada com sucesso! Pedido aguardando entrega.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("DB Error na separação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 403, 404], true)) {
        $code = 400;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
