<?php
// Caminho: faz_bem_v2/api_checkout_v2.php
session_start();
if (ob_get_length()) ob_clean();
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

    // Validações locais críticas antes de efetuar a cobrança
    if ($pedidoModel->verificarPedidoExistenteSemana($usuario_id)) {
        throw new Exception("Você já realizou um pedido esta semana. O limite é de apenas um pedido por pessoa.");
    }

    // Integração com Mercado Pago (Checkout Transparente)
    $payment_response = null;
    if (isset($data['mercado_pago_data'])) {
        require_once __DIR__ . '/app/MercadoPagoService.php';
        $mpService = new MercadoPagoService();
        $mpData = $data['mercado_pago_data'];
        
        $paymentPayload = [
            "transaction_amount" => (float) $valor_total,
            "description" => "Pedido Cesta Faz Bem",
            "payment_method_id" => $mpData['payment_method_id'] ?? null,
            "payer" => [
                "email" => $mpData['payer']['email'] ?? ''
            ]
        ];

        // Se for Cartão de Crédito
        if (isset($mpData['token'])) {
            $paymentPayload["token"] = $mpData['token'];
            $paymentPayload["installments"] = $mpData['installments'] ?? 1;
            if (isset($mpData['issuer_id'])) {
                $paymentPayload["issuer_id"] = $mpData['issuer_id'];
            }
        }

        // Dados do pagador (CPF)
        if (isset($mpData['payer']['identification'])) {
            $paymentPayload['payer']['identification'] = $mpData['payer']['identification'];
        }

        $mpResult = $mpService->createPayment($paymentPayload);

        // O Mercado Pago retorna 200 OK ou 201 Created para sucesso.
        if ($mpResult['status'] !== 200 && $mpResult['status'] !== 201) {
            $errorMsg = $mpResult['response']['message'] ?? ($mpResult['response']['error'] ?? 'Erro desconhecido no Mercado Pago.');
            throw new Exception("Falha ao processar pagamento no Mercado Pago: " . $errorMsg);
        }

        $payment_response = $mpResult['response'];
        $forma_pagamento = 'Mercado Pago - ' . ($mpData['payment_method_id'] ?? 'Online');
    }

    $mpPaymentId = null;
    $status_pagamento = 'Pendente';
    if (isset($payment_response)) {
        $mpPaymentId = $payment_response['id'] ?? null;
        if (isset($payment_response['status']) && $payment_response['status'] === 'approved') {
            $status_pagamento = 'Pago';
        }
    }

    $numero_pedido = $pedidoModel->criarPedido($usuario_id, $valor_total, $forma_pagamento, $carrinho, $mpPaymentId, $status_pagamento);

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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>