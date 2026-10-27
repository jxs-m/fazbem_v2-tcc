<?php
// Caminho: api_mp_webhook.php

ini_set('log_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/app/Env.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/MercadoPagoService.php';
require_once __DIR__ . '/app/Models/Pedido.php';

Env::load(__DIR__ . '/.env');

$paymentId = null;

// 1. Verificar parâmetros GET (IPN)
if (isset($_GET['topic']) && $_GET['topic'] === 'payment' && isset($_GET['id'])) {
    $paymentId = $_GET['id'];
} elseif (isset($_GET['type']) && $_GET['type'] === 'payment' && isset($_GET['data_id'])) {
    $paymentId = $_GET['data_id'];
} elseif (isset($_GET['id'])) {
    $paymentId = $_GET['id'];
}

// 2. Verificar corpo JSON (Webhooks) sem registrar payload bruto em logs
if (!$paymentId) {
    $jsonStr = file_get_contents('php://input');
    if ($jsonStr) {
        $data = json_decode($jsonStr, true);
        if (is_array($data)) {
            if (isset($data['type']) && $data['type'] === 'payment') {
                $paymentId = $data['data']['id'] ?? null;
            } elseif (isset($data['action']) && strpos((string) $data['action'], 'payment') !== false) {
                $paymentId = $data['data']['id'] ?? null;
            }
        }
    }
}

$paymentId = filter_var($paymentId, FILTER_VALIDATE_INT);

if (!$paymentId || $paymentId <= 0) {
    error_log("Webhook MP: ID de pagamento ausente ou inválido.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de pagamento inválido ou não fornecido.']);
    exit;
}

// 3. Verificar Assinatura do Webhook (x-signature) conforme o ambiente
$ambiente = Env::getAmbiente();
$webhookSecret = trim((string) Env::get('MP_WEBHOOK_SECRET', ''));

if ($ambiente === 'producao' && $webhookSecret === '') {
    error_log("Webhook MP [producao]: MP_WEBHOOK_SECRET não configurado.");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração de webhook indisponível.']);
    exit;
}

if ($webhookSecret !== '') {
    $xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

    $ts = '';
    $v1 = '';

    if ($xSignature !== '') {
        $parts = explode(',', $xSignature);
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                if ($kv[0] === 'ts') $ts = $kv[1];
                if ($kv[0] === 'v1') $v1 = $kv[1];
            }
        }
    }

    $manifest = "id:{$paymentId};request-id:{$xRequestId};ts:{$ts};";
    $hmac = hash_hmac('sha256', $manifest, $webhookSecret);

    if ($v1 === '' || !hash_equals($hmac, $v1)) {
        error_log("Webhook MP: Assinatura inválida para payment_id={$paymentId}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Assinatura do webhook inválida.']);
        exit;
    }
}

try {
    $mpService = new MercadoPagoService();
    $result = $mpService->getPayment($paymentId);

    if ($result['error_type'] === 'transport' || $result['error_type'] === 'config') {
        error_log("Webhook MP: Falha temporária ({$result['error_type']}) ao consultar payment_id={$paymentId}");
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Serviço temporariamente indisponível para consulta do pagamento.']);
        exit;
    }

    if ($result['status'] !== 200) {
        error_log("Webhook MP: Pagamento {$paymentId} não encontrado no provedor. HTTP={$result['status']}");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pagamento não encontrado no Mercado Pago.']);
        exit;
    }

    $paymentInfo = $result['response'] ?? [];
    $remoteId = filter_var($paymentInfo['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$remoteId || (int) $remoteId !== (int) $paymentId) {
        error_log("Webhook MP: Divergência de identificador na consulta do pagamento {$paymentId}.");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Identificador do pagamento inconsistente.']);
        exit;
    }

    $currencyId = strtoupper(trim((string) ($paymentInfo['currency_id'] ?? 'BRL')));
    if ($currencyId !== 'BRL') {
        error_log("Webhook MP: Moeda inválida ({$currencyId}) para payment_id={$paymentId}");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Moeda do pagamento inválida.']);
        exit;
    }

    $status = strtolower(trim((string) ($paymentInfo['status'] ?? '')));
    $externalRef = trim((string) ($paymentInfo['external_reference'] ?? ''));
    $paymentMethodId = trim((string) ($paymentInfo['payment_method_id'] ?? 'Online'));
    $mpAmount = isset($paymentInfo['transaction_amount']) ? round(floatval($paymentInfo['transaction_amount']), 2) : null;
    $metaUsuarioId = isset($paymentInfo['metadata']['usuario_id']) ? filter_var($paymentInfo['metadata']['usuario_id'], FILTER_VALIDATE_INT) : null;

    // 4. Identificar tipo e ID do recurso via external_reference tipada ("fatura:ID" ou "pedido:ID") ou fallback
    $recursoTipo = null;
    $recursoId = null;

    if (preg_match('/^(fatura|pedido):(\d+)$/i', $externalRef, $matches)) {
        $recursoTipo = strtolower($matches[1]);
        $recursoId = (int) $matches[2];
    } elseif ($externalRef !== '' && ctype_digit($externalRef)) {
        $recursoTipo = 'fatura';
        $recursoId = (int) $externalRef;
    }

    $pdo = Database::getConexao();
    $pdo->beginTransaction();

    $recurso = null;
    $paymentIdStr = (string) $paymentId;

    // Busca com lock FOR UPDATE conforme tipo identificado ou fallback por transacao_id
    if ($recursoTipo === 'fatura' && $recursoId > 0) {
        $stmt = $pdo->prepare("SELECT id, usuario_id, valor_mensalidade, valor_total, status, transacao_id, mp_status FROM faturas_mensais WHERE id = ? FOR UPDATE");
        $stmt->execute([$recursoId]);
        $recurso = $stmt->fetch();
    } elseif ($recursoTipo === 'pedido' && $recursoId > 0) {
        $stmt = $pdo->prepare("SELECT id, usuario_id, valor_total, status_pagamento, transacao_id, mp_status FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->execute([$recursoId]);
        $recurso = $stmt->fetch();
    }

    if (!$recurso) {
        $stmtFatTx = $pdo->prepare("SELECT id, usuario_id, valor_mensalidade, valor_total, status, transacao_id, mp_status FROM faturas_mensais WHERE transacao_id = ? FOR UPDATE");
        $stmtFatTx->execute([$paymentIdStr]);
        $recurso = $stmtFatTx->fetch();
        if ($recurso) {
            $recursoTipo = 'fatura';
            $recursoId = (int) $recurso['id'];
        }
    }

    if (!$recurso) {
        $stmtPedTx = $pdo->prepare("SELECT id, usuario_id, valor_total, status_pagamento, transacao_id, mp_status FROM pedidos WHERE transacao_id = ? FOR UPDATE");
        $stmtPedTx->execute([$paymentIdStr]);
        $recurso = $stmtPedTx->fetch();
        if ($recurso) {
            $recursoTipo = 'pedido';
            $recursoId = (int) $recurso['id'];
        }
    }

    if (!$recurso) {
        $pdo->rollBack();
        error_log("Webhook MP: Recurso local não encontrado para payment_id={$paymentId} ref={$externalRef}");
        http_response_code(200);
        echo json_encode(['success' => true, 'reconciled' => false, 'message' => 'Recurso local não encontrado.']);
        exit;
    }

    // 5. Validar valor, usuário e consistência com o estado local
    $localAmount = round(floatval($recurso['valor_total']), 2);
    if ($mpAmount === null || abs($mpAmount - $localAmount) > 0.01) {
        $pdo->rollBack();
        error_log("Webhook MP: Divergência de valor para {$recursoTipo}#{$recursoId} payment_id={$paymentId} (esperado={$localAmount}, recebido=" . ($mpAmount ?? 'null') . ")");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valor do pagamento diverge do registro local.']);
        exit;
    }

    $localUsuarioId = (int) $recurso['usuario_id'];
    if ($metaUsuarioId && (int) $metaUsuarioId !== $localUsuarioId) {
        $pdo->rollBack();
        error_log("Webhook MP: Divergência de usuário para {$recursoTipo}#{$recursoId} payment_id={$paymentId}");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuário do pagamento diverge do recurso local.']);
        exit;
    }

    $stmtCheckUser = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmtCheckUser->execute([$localUsuarioId]);
    if (!$stmtCheckUser->fetch()) {
        $pdo->rollBack();
        error_log("Webhook MP: Usuário {$localUsuarioId} inexistente para {$recursoTipo}#{$recursoId}");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuário associado inválido.']);
        exit;
    }

    $formaPagamento = 'Mercado Pago - ' . ucfirst($paymentMethodId);
    $localStatus = $recursoTipo === 'fatura' ? $recurso['status'] : $recurso['status_pagamento'];
    $localTxId = $recurso['transacao_id'] !== null ? (string) $recurso['transacao_id'] : null;
    $localMpStatus = $recurso['mp_status'] ?? null;

    // Notificação exatamente duplicada (mesma transação e mesmo mp_status já aplicado)
    if ($localTxId === $paymentIdStr && $localMpStatus === $status) {
        $pdo->commit();
        http_response_code(200);
        echo json_encode(['success' => true, 'reconciled' => true, 'duplicate' => true, 'resource' => $recursoTipo, 'id' => $recursoId]);
        exit;
    }

    // 6. Aplicar transição de reconciliação conforme status do pagamento
    if (in_array($status, ['approved', 'accredited'], true)) {
        if ($recursoTipo === 'fatura') {
            if ($localStatus !== 'Pago') {
                $stmtUpd = $pdo->prepare(
                    "UPDATE faturas_mensais 
                     SET status = 'Pago', 
                         pago_em = IFNULL(pago_em, NOW()), 
                         transacao_id = ?, 
                         forma_pagamento = ?, 
                         mp_status = ? 
                     WHERE id = ?"
                );
                $stmtUpd->execute([$paymentIdStr, $formaPagamento, $status, $recursoId]);

                if (floatval($recurso['valor_mensalidade']) > 0) {
                    $stmtSub = $pdo->prepare("SELECT status FROM assinaturas WHERE usuario_id = ? FOR UPDATE");
                    $stmtSub->execute([$localUsuarioId]);
                    if ($stmtSub->fetchColumn() === 'Cancelada') {
                        $pdo->prepare("UPDATE assinaturas SET status = 'Ativa' WHERE usuario_id = ?")
                            ->execute([$localUsuarioId]);
                    }
                }
            } else {
                // Já estava paga; apenas garante mp_status e transacao_id se ainda não preenchidos
                $pdo->prepare("UPDATE faturas_mensais SET transacao_id = COALESCE(transacao_id, ?), mp_status = ? WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
            }
        } else {
            if ($localStatus !== 'Pago') {
                $stmtUpd = $pdo->prepare(
                    "UPDATE pedidos 
                     SET status_pagamento = 'Pago', 
                         transacao_id = ?, 
                         forma_pagamento = ?, 
                         mp_status = ? 
                     WHERE id = ?"
                );
                $stmtUpd->execute([$paymentIdStr, $formaPagamento, $status, $recursoId]);
            } else {
                $pdo->prepare("UPDATE pedidos SET transacao_id = COALESCE(transacao_id, ?), mp_status = ? WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
            }
        }
    } elseif (in_array($status, ['pending', 'in_process', 'authorized'], true)) {
        // Não rebaixa recurso que já consta como Pago
        if ($localStatus !== 'Pago') {
            if ($recursoTipo === 'fatura') {
                $pdo->prepare("UPDATE faturas_mensais SET status = 'Pendente', transacao_id = ?, forma_pagamento = ?, mp_status = ? WHERE id = ?")
                    ->execute([$paymentIdStr, $formaPagamento, $status, $recursoId]);
            } else {
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Pendente', transacao_id = ?, forma_pagamento = ?, mp_status = ? WHERE id = ?")
                    ->execute([$paymentIdStr, $formaPagamento, $status, $recursoId]);
            }
        }
    } elseif (in_array($status, ['rejected', 'cancelled'], true)) {
        // Apenas aplica rejeição/cancelamento se o recurso não estiver Pago por outra transação aprovada
        if ($localStatus !== 'Pago' && ($localTxId === null || $localTxId === $paymentIdStr)) {
            if ($recursoTipo === 'fatura') {
                // Fatura mensal continua Pendente para permitir nova tentativa de pagamento, liberando idempotency_key
                $pdo->prepare("UPDATE faturas_mensais SET status = 'Pendente', transacao_id = ?, mp_status = ?, idempotency_key = NULL WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
            } else {
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Cancelado', transacao_id = ?, mp_status = ?, idempotency_key = NULL, semana_ativa_chave = NULL WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
                $pedidoModel = new Pedido();
                $pedidoModel->estornarEstoquePedido($recursoId, "Webhook MP ({$status})");
            }
        }
    } elseif (in_array($status, ['refunded', 'charged_back'], true)) {
        // Estorno ou chargeback da transação associada
        if ($localTxId === null || $localTxId === $paymentIdStr) {
            if ($recursoTipo === 'fatura') {
                $pdo->prepare("UPDATE faturas_mensais SET status = 'Cancelado', pago_em = NULL, transacao_id = ?, mp_status = ? WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
            } else {
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Cancelado', transacao_id = ?, mp_status = ?, semana_ativa_chave = NULL WHERE id = ?")
                    ->execute([$paymentIdStr, $status, $recursoId]);
                $pedidoModel = new Pedido();
                $pedidoModel->estornarEstoquePedido($recursoId, "Webhook MP ({$status})");
            }
        }
    }

    $pdo->commit();
    error_log("Webhook MP reconciliado: recurso={$recursoTipo}#{$recursoId} payment_id={$paymentId} status={$status}");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reconciled' => true,
        'duplicate' => false,
        'resource' => $recursoTipo,
        'id' => $recursoId,
        'payment_status' => $status
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Webhook MP Exception [payment_id={$paymentId}]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar notificação de pagamento.']);
}
?>
