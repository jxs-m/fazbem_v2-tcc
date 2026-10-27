<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_checkout_v2.php

header('Content-Type: application/json');

Security::checkCSRF();
Security::checkRateLimit(15, 60);

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
    $forma_pagamento = $data['pagamento'];
    $carrinho = $data['itens']; 
    $pedidoModel = new Pedido();
    
    // Calcula o total real baseado no banco de dados, ignorando o client
    $processamento = $pedidoModel->processarCarrinho($carrinho);
    $valor_total_real = $processamento['total_calculado'];
    $itens_processados = $processamento['itens_processados'];

    // Verificar se o usuário possui uma assinatura ativa
    require_once __DIR__ . '/app/Models/Assinatura.php';
    $assinaturaModel = new Assinatura();
    $assinatura = $assinaturaModel->buscarPorUsuario($usuario_id);
    if (!$assinatura || $assinatura['status'] !== 'Ativa') {
        throw new Exception("Você precisa ter uma assinatura ativa para fazer pedidos adicionais. Por favor, ative sua assinatura na seção 'Meu Perfil'.");
    }

    // Verificar se o usuário possui alguma fatura de assinatura pendente
    require_once __DIR__ . '/app/Database.php';
    require_once __DIR__ . '/app/MercadoPagoService.php';
    $pdo = Database::getConexao();
    $stmtFatPend = $pdo->prepare("SELECT id, mes_referencia, valor_total FROM faturas_mensais WHERE usuario_id = ? AND status = 'Pendente' AND valor_mensalidade > 0 ORDER BY mes_referencia ASC LIMIT 1");
    $stmtFatPend->execute([$usuario_id]);
    $faturaPendente = $stmtFatPend->fetch();

    if ($faturaPendente) {
        $valF = number_format(floatval($faturaPendente['valor_total']), 2, ',', '.');
        throw new Exception("Você possui a fatura de assinatura de {$faturaPendente['mes_referencia']} em aberto (R$ {$valF}). Por favor, quite a fatura pendente na seção 'Minhas Faturas' do seu perfil antes de realizar novos pedidos.");
    }

    $usaMercadoPago = isset($data['mercado_pago_data']) && is_array($data['mercado_pago_data']);
    $tipo_pedido = (isset($data['tipo_pedido']) && in_array($data['tipo_pedido'], ['Avulso', 'Extra'], true)) ? $data['tipo_pedido'] : 'Extra';
    $clientKey = trim((string) ($data['idempotency_key'] ?? ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '')));
    $contextoChave = $clientKey !== '' ? $clientKey : (date('oW') . '_' . number_format($valor_total_real, 2, '.', ''));
    $idempotencyKey = MercadoPagoService::buildIdempotencyKey('pedido_u' . $usuario_id, date('oW'), $contextoChave);

    // 1. Transação local para validar concorrência semanal e persistir chave de idempotência ANTES do Mercado Pago
    $pdo->beginTransaction();
    try {
        // Serializa operações concorrentes do mesmo cliente
        $stmtLockUser = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? FOR UPDATE");
        $stmtLockUser->execute([$usuario_id]);

        $pedidoExistente = $pedidoModel->buscarPedidoAtivoSemanaOuChave($usuario_id, $idempotencyKey);

        if ($pedidoExistente) {
            $mpStatusExistente = $pedidoExistente['mp_status'] ?? null;
            if ($pedidoExistente['status_pagamento'] === 'Pago' || in_array($mpStatusExistente, ['approved', 'in_process', 'pending', 'processing'], true)) {
                throw new Exception("Já existe um pagamento aprovado ou em processamento para o pedido desta semana.");
            }

            if ($usaMercadoPago && $mpStatusExistente === 'timeout' && !empty($pedidoExistente['idempotency_key'])) {
                // Reutiliza o pedido e a chave de idempotência após timeout de transporte
                $numero_pedido = (int) $pedidoExistente['id'];
                $idempotencyKey = $pedidoExistente['idempotency_key'];
                $valor_total_real = floatval($pedidoExistente['valor_total']);
                $pedidoModel->atualizarPagamentoMercadoPago($numero_pedido, $usuario_id, 'Pendente', 'processing', null, null, false);
            } else {
                throw new Exception("Você já realizou um pedido esta semana. O limite é de apenas um pedido por pessoa.");
            }
        } else {
            $mpStatusInicial = $usaMercadoPago ? 'processing' : null;
            $numero_pedido = $pedidoModel->criarPedido(
                $usuario_id,
                $valor_total_real,
                $forma_pagamento,
                $itens_processados,
                null,
                'Pendente',
                $idempotencyKey,
                $mpStatusInicial,
                $tipo_pedido
            );
        }

        $pdo->commit();
    } catch (PDOException $LockEx) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $sqlState = $LockEx->getCode();
        $driverCode = $LockEx->errorInfo[1] ?? null;
        if ($sqlState === '23000' || $driverCode === 1062) {
            throw new Exception("Operação de pedido já registrada ou em processamento. Aguarde antes de tentar novamente.");
        }
        throw $LockEx;
    } catch (Exception $validationEx) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $validationEx;
    }

    // 2. Integração com Mercado Pago (Checkout Transparente) usando a chave persistida e external_reference tipada
    if ($usaMercadoPago) {
        $mpService = new MercadoPagoService();
        $mpData = $data['mercado_pago_data'];
        $forma_pagamento = 'Mercado Pago - ' . ($mpData['payment_method_id'] ?? 'Online');

        $paymentPayload = [
            "transaction_amount" => (float) $valor_total_real,
            "description" => "Pedido Cesta Faz Bem #" . $numero_pedido,
            "external_reference" => "pedido:" . $numero_pedido,
            "metadata" => [
                "recurso_tipo" => "pedido",
                "recurso_id" => (int) $numero_pedido,
                "usuario_id" => (int) $usuario_id
            ],
            "payment_method_id" => $mpData['payment_method_id'] ?? null,
            "payer" => [
                "email" => $mpData['payer']['email'] ?? ''
            ]
        ];

        // Se for Cartão de Crédito
        if (isset($mpData['token'])) {
            $paymentPayload["token"] = $mpData['token'];
            $paymentPayload["installments"] = isset($mpData['installments']) ? (int) $mpData['installments'] : 1;
            if (isset($mpData['issuer_id'])) {
                $paymentPayload["issuer_id"] = $mpData['issuer_id'];
            }
        }

        // Dados do pagador (CPF)
        if (isset($mpData['payer']['identification'])) {
            $paymentPayload['payer']['identification'] = $mpData['payer']['identification'];
        }

        $mpResult = $mpService->createPayment($paymentPayload, $idempotencyKey);

        if ($mpResult['error_type'] === 'transport') {
            // Mantém pedido e idempotency_key com mp_status='timeout' para permitir retry seguro ou conciliação via webhook
            $pedidoModel->atualizarPagamentoMercadoPago($numero_pedido, $usuario_id, 'Pendente', 'timeout', null, $forma_pagamento, false);
            throw new Exception($mpResult['message']);
        }

        if (!$mpResult['success']) {
            $statusFalha = $mpResult['payment_status'] ?? 'rejected';
            // Cancela a tentativa recusada e libera a chave para que o cliente possa tentar novamente com outros dados
            $pedidoModel->atualizarPagamentoMercadoPago($numero_pedido, $usuario_id, 'Cancelado', $statusFalha, null, $forma_pagamento, true);
            throw new Exception("Falha ao processar pagamento no Mercado Pago: " . $mpResult['message']);
        }

        $payment_response = $mpResult['response'] ?? [];
        $mpPaymentId = isset($payment_response['id']) ? (string) $payment_response['id'] : null;
        $mpPaymentStatus = $mpResult['payment_status'] ?? ($payment_response['status'] ?? 'pending');
        $status_pagamento = ($mpPaymentStatus === 'approved') ? 'Pago' : 'Pendente';

        $pedidoModel->atualizarPagamentoMercadoPago(
            $numero_pedido,
            $usuario_id,
            $status_pagamento,
            $mpPaymentStatus,
            $mpPaymentId,
            $forma_pagamento,
            false
        );
    }

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