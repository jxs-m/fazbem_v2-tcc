<?php
// Caminho: api_mp_webhook.php

// Habilitar logs de erro detalhados para o webhook
ini_set('log_errors', 1);
error_log("Webhook MP recebido!");

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/MercadoPagoService.php';

$paymentId = null;

// 1. Verificar parâmetros GET (IPN)
if (isset($_GET['topic']) && $_GET['topic'] === 'payment' && isset($_GET['id'])) {
    $paymentId = $_GET['id'];
} elseif (isset($_GET['type']) && $_GET['type'] === 'payment' && isset($_GET['data_id'])) {
    $paymentId = $_GET['data_id'];
} elseif (isset($_GET['id'])) {
    $paymentId = $_GET['id'];
}

// 2. Verificar corpo JSON (Webhooks)
if (!$paymentId) {
    $jsonStr = file_get_contents('php://input');
    if ($jsonStr) {
        $data = json_decode($jsonStr, true);
        error_log("Webhook MP Payload JSON: " . $jsonStr);
        if (isset($data['type']) && $data['type'] === 'payment') {
            $paymentId = $data['data']['id'] ?? null;
        } elseif (isset($data['action']) && strpos($data['action'], 'payment') !== false) {
            $paymentId = $data['data']['id'] ?? null;
        }
    }
}

$paymentId = filter_var($paymentId, FILTER_VALIDATE_INT);

if (!$paymentId) {
    error_log("Webhook MP: ID de pagamento não encontrado ou inválido na notificação.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de pagamento inválido ou não fornecido.']);
    exit;
}

// 3. Verificar Assinatura do Webhook (x-signature)
require_once __DIR__ . '/app/Env.php';
$webhookSecret = Env::get('MP_WEBHOOK_SECRET');

if (!empty($webhookSecret)) {
    $xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

    $ts = '';
    $v1 = '';
    
    if ($xSignature) {
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

    if (!hash_equals($hmac, $v1)) {
        error_log("Webhook MP: Assinatura inválida! Manifest: $manifest");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Assinatura do webhook inválida.']);
        exit;
    }
}

error_log("Webhook MP: Processando pagamento ID $paymentId");

try {
    $mpService = new MercadoPagoService();
    $result = $mpService->getPayment($paymentId);

    if ($result['status'] !== 200) {
        error_log("Webhook MP: Erro ao buscar pagamento $paymentId no Mercado Pago. Status HTTP: " . $result['status']);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pagamento não encontrado no Mercado Pago.']);
        exit;
    }

    $paymentInfo = $result['response'] ?? [];
    $status = $paymentInfo['status'] ?? '';
    $statusDetail = $paymentInfo['status_detail'] ?? '';
    $f_id = $paymentInfo['external_reference'] ?? null;
    $paymentMethodId = $paymentInfo['payment_method_id'] ?? 'Online';

    error_log("Webhook MP: Pagamento ID $paymentId | Status: $status | External Reference (Fatura ID): $f_id");

    // Processar apenas se o status for 'approved'
    if ($status === 'approved') {
        $pdo = Database::getConexao();

        // Buscar fatura correspondente
        $fatura = null;
        if ($f_id) {
            $stmt = $pdo->prepare("SELECT id, status, usuario_id FROM faturas_mensais WHERE id = ?");
            $stmt->execute([$f_id]);
            $fatura = $stmt->fetch();
        }

        // Fallback: se não encontrou por external_reference, busca por transacao_id
        if (!$fatura) {
            $stmt = $pdo->prepare("SELECT id, status, usuario_id FROM faturas_mensais WHERE transacao_id = ?");
            $stmt->execute([$paymentId]);
            $fatura = $stmt->fetch();
        }

        if ($fatura) {
            if ($fatura['status'] !== 'Pago') {
                $faturaIdReal = $fatura['id'];
                $u_id = $fatura['usuario_id'];
                $formaPagamento = 'Mercado Pago - ' . ucfirst($paymentMethodId);

                $pdo->beginTransaction();
                try {
                    // Atualizar status da fatura para Pago
                    $stmtUpdate = $pdo->prepare("UPDATE faturas_mensais SET status = 'Pago', pago_em = NOW(), transacao_id = ?, forma_pagamento = ? WHERE id = ?");
                    $stmtUpdate->execute([$paymentId, $formaPagamento, $faturaIdReal]);

                    // Ativar assinatura caso esteja Cancelada
                    $stmtSub = $pdo->prepare("SELECT status FROM assinaturas WHERE usuario_id = ?");
                    $stmtSub->execute([$u_id]);
                    $subStatus = $stmtSub->fetchColumn();

                    if ($subStatus === 'Cancelada') {
                        $pdo->prepare("UPDATE assinaturas SET status = 'Ativa' WHERE usuario_id = ?")
                            ->execute([$u_id]);
                        error_log("Webhook MP: Assinatura do usuário $u_id reativada.");
                    }

                    $pdo->commit();
                    error_log("Webhook MP: Fatura #$faturaIdReal marcada como Paga com sucesso.");
                } catch (Exception $dbEx) {
                    $pdo->rollBack();
                    error_log("Webhook MP Error: Falha ao atualizar banco de dados para fatura #$faturaIdReal: " . $dbEx->getMessage());
                    http_response_code(500);
                    exit;
                }
            } else {
                error_log("Webhook MP: Fatura #" . $fatura['id'] . " já está marcada como Paga.");
            }
        } else {
            error_log("Webhook MP: Nenhuma fatura local encontrada correspondente ao pagamento $paymentId.");
        }
    } else {
        error_log("Webhook MP: Pagamento ID $paymentId com status '$status' não processado (somente approved é processado).");
    }

    // Mercado Pago exige resposta 200 ou 201 para confirmar recebimento
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Webhook MP Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
