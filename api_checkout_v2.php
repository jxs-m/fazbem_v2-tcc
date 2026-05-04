<?php
// Caminho: faz_bem_v2/api_checkout_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Pedido.php';
require_once __DIR__ . '/app/Models/Producao.php';

$producaoModel = new Producao();
if (!$producaoModel->catalogoAberto()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'As vendas estão encerradas para este ciclo logístico. O limite de kits foi atingido ou o horário expirou.']);
    exit;
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você precisa fazer login para finalizar a compra.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['itens']) || !is_array($data['itens'])) {
        throw new Exception("O seu carrinho está vazio ou inválido.");
    }
    if (empty($data['total']) || empty($data['pagamento'])) {
        throw new Exception("Faltam informações de pagamento ou valor total.");
    }

    $usuario_id = $_SESSION['usuario_id'];
    $valor_total = $data['total'];
    $forma_pagamento = $data['pagamento'];
    $carrinho = $data['itens']; 
    $pedidoModel = new Pedido();
    $numero_pedido = $pedidoModel->criarPedido($usuario_id, $valor_total, $forma_pagamento, $carrinho);

    echo json_encode([
        'success' => true, 
        'message' => 'Pedido realizado com sucesso!',
        'pedido_id' => $numero_pedido
    ]);

} catch (PDOException $e) {
    error_log("DB Error no checkout: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar pedido.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>