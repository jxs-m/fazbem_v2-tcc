<?php
// Caminho: faz_bem_v2/api_checkout_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Pedido.php';

// Segurança: Só clientes logados podem finalizar compra
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
    // Recebe o carrinho que vem do frontend
    $data = json_decode(file_get_contents('php://input'), true);

    // Validações básicas usando os nomes exatos do seu JavaScript
    if (empty($data['itens']) || !is_array($data['itens'])) {
        throw new Exception("O seu carrinho está vazio ou inválido.");
    }
    if (empty($data['total']) || empty($data['pagamento'])) {
        throw new Exception("Faltam informações de pagamento ou valor total.");
    }

    // Mapeia os dados do JS para as variáveis que o nosso Model usa
    $usuario_id = $_SESSION['usuario_id'];
    $valor_total = $data['total'];
    $forma_pagamento = $data['pagamento'];
    $carrinho = $data['itens']; // O array com os produtos

    // Chama o Model para fazer a mágica com segurança (Transação)
    $pedidoModel = new Pedido();
    $numero_pedido = $pedidoModel->criarPedido($usuario_id, $valor_total, $forma_pagamento, $carrinho);

    echo json_encode([
        'success' => true, 
        'message' => 'Pedido realizado com sucesso!',
        'pedido_id' => $numero_pedido
    ]);

} catch (Exception $e) {
// ... resto do código continua igual{
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar pedido: ' . $e->getMessage()]);
}
?>