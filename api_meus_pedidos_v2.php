<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_meus_pedidos_v2.php

header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Pedido.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']); 
    exit;
}

Security::checkRateLimit(60, 60);

try {
    $pedidoModel = new Pedido();
    $acao = $_GET['acao'] ?? '';
    
    $acoesPermitidas = ['', 'pedido_semana', 'ultimo_pedido'];
    if (!in_array($acao, $acoesPermitidas, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
        exit;
    }

    if ($acao === 'pedido_semana') {
        $pedido = $pedidoModel->buscarPedidoSemana($_SESSION['usuario_id']);
        if ($pedido) {
            $itens = $pedidoModel->buscarItens($pedido['id']);
            echo json_encode(['success' => true, 'pedido' => $pedido, 'itens' => $itens]);
        } else {
            echo json_encode(['success' => true, 'pedido' => null, 'itens' => []]);
        }
        exit;
    }

    if ($acao === 'ultimo_pedido') {
        $ultimo = $pedidoModel->buscarUltimoPedidoAnterior($_SESSION['usuario_id']);
        if ($ultimo) {
            $itens = $pedidoModel->buscarItens($ultimo['id']);
            echo json_encode(['success' => true, 'itens' => $itens]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nenhum pedido de semanas anteriores foi encontrado.']);
        }
        exit;
    }

    $pedidos = $pedidoModel->buscarPorUsuario($_SESSION['usuario_id']);

    echo json_encode(['success' => true, 'data' => $pedidos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos.']);
}
?>