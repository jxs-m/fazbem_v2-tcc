<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_faturamento_v2.php

header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';

// Verificação simples de login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$acao = $_GET['acao'] ?? '';
$pdo = Database::getConexao();

try {
    switch ($acao) {
        case 'gerar_faturas':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                throw new Exception("Método não permitido. Utilize POST.");
            }
            Security::checkCSRF();
            if ($_SESSION['tipo_usuario'] !== 'admin') {
                throw new Exception("Apenas admins podem gerar faturas.");
            }

            $mes_referencia = date('Y-m'); // Ex: 2026-05

            // Buscar todos os clientes com assinaturas Ativas ou Pausadas
            $sql = "SELECT a.usuario_id, a.valor_mensal, u.saldo_compensacao 
                    FROM assinaturas a 
                    JOIN usuarios u ON a.usuario_id = u.id 
                    WHERE a.status IN ('Ativa', 'Pausada')";
            $stmt = $pdo->query($sql);
            $assinantes = $stmt->fetchAll();

            $faturas_geradas = 0;
            foreach ($assinantes as $ass) {
                $u_id = $ass['usuario_id'];
                
                // Verifica se já gerou fatura para este mes
                $check = $pdo->prepare("SELECT id FROM faturas_mensais WHERE usuario_id = ? AND mes_referencia = ?");
                $check->execute([$u_id, $mes_referencia]);
                if ($check->rowCount() > 0) continue;

                $valor_mensalidade = floatval($ass['valor_mensal']);
                $saldo = floatval($ass['saldo_compensacao']);

                // Buscar total de extras pendentes e que já passaram pela balança (Aguardando Entrega, Saiu para entrega, Entregue)
                $sqlExtras = "SELECT IFNULL(SUM(valor_total), 0) as total_extras 
                              FROM pedidos 
                              WHERE usuario_id = ? AND tipo_pedido IN ('Extra', 'Avulso') AND status_pagamento = 'Pendente' AND status_entrega != 'Em separação'";
                $stmtExt = $pdo->prepare($sqlExtras);
                $stmtExt->execute([$u_id]);
                $valor_extras = floatval($stmtExt->fetchColumn());

                $subtotal = $valor_mensalidade + $valor_extras;
                $desconto = min($saldo, $subtotal); // Não desconta mais do que a fatura
                $total = $subtotal - $desconto;

                $pdo->beginTransaction();
                
                // Insere Fatura
                $sqlInsert = "INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([$u_id, $mes_referencia, $valor_mensalidade, $valor_extras, $desconto, $total]);
                
                // Abate o saldo do usuario
                if ($desconto > 0) {
                    $sqlUpdateSaldo = "UPDATE usuarios SET saldo_compensacao = saldo_compensacao - ? WHERE id = ?";
                    $pdo->prepare($sqlUpdateSaldo)->execute([$desconto, $u_id]);
                    
                    // Registra debito na carteira
                    $pdo->prepare("INSERT INTO transacoes_financeiras (usuario_id, tipo, valor, motivo) VALUES (?, 'Debito', ?, ?)")
                        ->execute([$u_id, $desconto, "Abatimento na Fatura de $mes_referencia"]);
                }

                // Marca pedidos como 'Faturado' (ou 'Pago' para não entrarem no próximo mês)
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Pago', obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Faturado em ', ?, ']') WHERE usuario_id = ? AND tipo_pedido IN ('Extra', 'Avulso') AND status_pagamento = 'Pendente'")
                    ->execute([$mes_referencia, $u_id]);

                $pdo->commit();
                $faturas_geradas++;
            }

            echo json_encode(['success' => true, 'message' => "Foram geradas $faturas_geradas faturas com sucesso."]);
            break;

        case 'minhas_faturas':
            if ($_SESSION['tipo_usuario'] !== 'cliente') throw new Exception("Apenas clientes.");
            $u_id = $_SESSION['usuario_id'];
            $stmt = $pdo->prepare("SELECT * FROM faturas_mensais WHERE usuario_id = ? ORDER BY id DESC");
            $stmt->execute([$u_id]);
            echo json_encode(['success' => true, 'faturas' => $stmt->fetchAll()]);
            break;

        case 'listar_faturas_admin':
            if ($_SESSION['tipo_usuario'] !== 'admin') throw new Exception("Apenas admins.");
            $sql = "SELECT f.*, u.nome as cliente 
                    FROM faturas_mensais f 
                    JOIN usuarios u ON f.usuario_id = u.id 
                    ORDER BY f.id DESC";
            $stmt = $pdo->query($sql);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'pagar_fatura':
            Security::checkCSRF();
            if ($_SESSION['tipo_usuario'] !== 'cliente') throw new Exception("Apenas clientes.");
            
            $rawData = file_get_contents('php://input');
            error_log("PAGAR_FATURA - METHOD: " . $_SERVER['REQUEST_METHOD'] . " | BODY: " . $rawData);
            
            $data = json_decode($rawData, true);
            $f_id = $data['fatura_id'] ?? null;
            if (!$f_id) throw new Exception("ID da fatura inválido.");

            $stmtF = $pdo->prepare("SELECT valor_total FROM faturas_mensais WHERE id = ? AND usuario_id = ? AND status != 'Pago'");
            $stmtF->execute([$f_id, $_SESSION['usuario_id']]);
            $fatura = $stmtF->fetch();
            if (!$fatura) throw new Exception("Fatura não encontrada ou já paga.");
            $valor_total = $fatura['valor_total'];

            if (isset($data['mercado_pago_data'])) {
                require_once __DIR__ . '/app/MercadoPagoService.php';
                $mpService = new MercadoPagoService();
                $mpData = $data['mercado_pago_data'];
                
                $paymentPayload = [
                    "transaction_amount" => (float) $valor_total,
                    "description" => "Pagamento de Fatura Mensal #" . $f_id,
                    "external_reference" => (string) $f_id,
                    "payment_method_id" => $mpData['payment_method_id'] ?? null,
                    "payer" => [
                        "email" => $mpData['payer']['email'] ?? ''
                    ]
                ];

                if (isset($mpData['token'])) {
                    $paymentPayload["token"] = $mpData['token'];
                    $paymentPayload["installments"] = isset($mpData['installments']) ? (int)$mpData['installments'] : 1;
                    if (isset($mpData['issuer_id'])) {
                        $paymentPayload["issuer_id"] = $mpData['issuer_id'];
                    }
                }

                if (isset($mpData['payer']['identification'])) {
                    $paymentPayload['payer']['identification'] = $mpData['payer']['identification'];
                }

                $mpResult = $mpService->createPayment($paymentPayload);

                if ($mpResult['status'] !== 200 && $mpResult['status'] !== 201) {
                    $errorMsg = $mpResult['response']['message'] ?? ($mpResult['response']['error'] ?? 'Erro no Mercado Pago.');
                    throw new Exception("Falha ao processar pagamento: " . $errorMsg);
                }

                $response = $mpResult['response'] ?? [];
                $paymentStatus = $response['status'] ?? 'rejected';

                if ($paymentStatus === 'rejected') {
                    $statusDetail = $response['status_detail'] ?? '';
                    $rejectionMessages = [
                        'cc_rejected_insufficient_amount' => 'Saldo/limite insuficiente no cartão.',
                        'cc_rejected_bad_filled_security_code' => 'Código de segurança (CVV) incorreto.',
                        'cc_rejected_bad_filled_date' => 'Data de vencimento do cartão incorreta.',
                        'cc_rejected_bad_filled_card_number' => 'Número do cartão inválido.',
                        'cc_rejected_call_for_authorize' => 'Pagamento recusado. Entre em contato com a operadora do cartão para autorizar.',
                        'cc_rejected_card_disabled' => 'O cartão informado está desativado.',
                        'cc_rejected_duplicated_payment' => 'Pagamento duplicado. Aguarde alguns instantes antes de tentar novamente.',
                        'cc_rejected_high_risk' => 'Pagamento recusado pela análise de segurança do Mercado Pago.',
                        'cc_rejected_blacklist' => 'O cartão está bloqueado para esta transação.',
                    ];
                    $msg = $rejectionMessages[$statusDetail] ?? 'Transação recusada pela operadora do cartão.';
                    throw new Exception($msg);
                } elseif ($paymentStatus !== 'approved' && $paymentStatus !== 'pending' && $paymentStatus !== 'in_process') {
                    throw new Exception("Pagamento não aprovado. Status: " . $paymentStatus);
                }
            }

            $mpPaymentId = null;
            $formaPagamento = 'Saldo/Crédito';
            $pixData = null;
            $statusFatura = 'Pago';

            if (isset($mpResult) && isset($mpResult['response'])) {
                $mpPaymentId = $mpResult['response']['id'] ?? null;
                $formaPagamento = 'Mercado Pago - ' . ($mpData['payment_method_id'] ?? 'Online');
                
                $response = $mpResult['response'];
                $paymentStatus = $response['status'] ?? 'approved';
                if ($paymentStatus === 'pending' || $paymentStatus === 'in_process') {
                    $statusFatura = 'Pendente';
                    if (isset($mpData['payment_method_id']) && $mpData['payment_method_id'] === 'pix') {
                        $pixData = [
                            'qr_code' => $response['point_of_interaction']['transaction_data']['qr_code'] ?? '',
                            'qr_code_base64' => $response['point_of_interaction']['transaction_data']['qr_code_base64'] ?? ''
                        ];
                    }
                }
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE faturas_mensais SET status = ?, pago_em = IF(? = 'Pago', NOW(), NULL), transacao_id = ?, forma_pagamento = ? WHERE id = ? AND usuario_id = ?")
                    ->execute([$statusFatura, $statusFatura, $mpPaymentId, $formaPagamento, $f_id, $_SESSION['usuario_id']]);

                if ($statusFatura === 'Pago') {
                    $stmtSub = $pdo->prepare("SELECT status FROM assinaturas WHERE usuario_id = ?");
                    $stmtSub->execute([$_SESSION['usuario_id']]);
                    $subStatus = $stmtSub->fetchColumn();
                    if ($subStatus === 'Cancelada') {
                        $pdo->prepare("UPDATE assinaturas SET status = 'Ativa' WHERE usuario_id = ?")
                            ->execute([$_SESSION['usuario_id']]);
                    }
                }

                $pdo->commit();
            } catch (Exception $dbEx) {
                $pdo->rollBack();
                error_log("CRITICAL ERROR: Fatura processada no MP mas falhou ao atualizar localmente. ID Fatura: " . $f_id . " | Erro: " . $dbEx->getMessage());
                throw new Exception("Ocorreu um erro ao registrar localmente. Entre em contato com o suporte informando a Fatura #" . $f_id);
            }
            
            echo json_encode([
                'success' => true,
                'status' => $statusFatura,
                'pix_data' => $pixData,
                'message' => $statusFatura === 'Pago' ? 'Fatura paga com sucesso!' : 'Aguardando pagamento do Pix.'
            ]);
            break;

        default:
            throw new Exception("Ação desconhecida.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = $e->getMessage();
    if (strpos($msg, 'Apenas admins') !== false || strpos($msg, 'Apenas clientes') !== false) {
        http_response_code(403);
    } else {
        http_response_code(400);
    }
    
    // Log the error internally, but don't expose PDO exceptions
    if ($e instanceof PDOException) {
        error_log("DB Error no faturamento: " . $msg);
        $msg = "Ocorreu um erro interno ao processar a fatura.";
    }
    
    echo json_encode(['success' => false, 'message' => $msg]);
}
?>
