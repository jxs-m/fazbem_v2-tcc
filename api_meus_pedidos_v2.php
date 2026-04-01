<?php
// Caminho: faz_bem_v2/api_meus_pedidos_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Pedido.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
}

try {
    $pedidoModel = new Pedido();
    $pedidos = $pedidoModel->buscarPorUsuario($_SESSION['usuario_id']);

    echo json_encode(['success' => true, 'data' => $pedidos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos.']);
}
?>