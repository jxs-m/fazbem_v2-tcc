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

Security::checkRateLimit(30, 60);

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
            $sql = "SELECT a.usuario_id 
                    FROM assinaturas a 
                    JOIN usuarios u ON a.usuario_id = u.id 
                    WHERE a.status IN ('Ativa', 'Pausada')";
            $stmt = $pdo->query($sql);
            $assinantes = $stmt->fetchAll();

            $faturas_geradas = 0;
            foreach ($assinantes as $ass) {
                $u_id = (int) $ass['usuario_id'];

                $pdo->beginTransaction();
                try {
                    // Bloqueia registro do assinante/usuário para leitura consistente do saldo e mensalidade
                    $stmtAss = $pdo->prepare(
                        "SELECT a.usuario_id, a.valor_mensal, u.saldo_compensacao 
                         FROM assinaturas a 
                         JOIN usuarios u ON a.usuario_id = u.id 
                         WHERE a.usuario_id = ? AND a.status IN ('Ativa', 'Pausada')
                         FOR UPDATE"
                    );
                    $stmtAss->execute([$u_id]);
                    $dadosAss = $stmtAss->fetch();
                    if (!$dadosAss) {
                        $pdo->rollBack();
                        continue;
                    }

                    // Verifica dentro da transação se já gerou fatura para este mês
                    $check = $pdo->prepare("SELECT id FROM faturas_mensais WHERE usuario_id = ? AND mes_referencia = ? FOR UPDATE");
                    $check->execute([$u_id, $mes_referencia]);
                    if ($check->fetch()) {
                        $pdo->rollBack();
                        continue;
                    }

                    $valor_mensalidade = round(floatval($dadosAss['valor_mensal']), 2);
                    $saldo = max(0.0, round(floatval($dadosAss['saldo_compensacao']), 2));

                    // Seleciona previamente apenas os pedidos elegíveis (já pesados e ainda não vinculados a fatura)
                    $sqlPedidosElegiveis = "SELECT id, valor_total 
                                            FROM pedidos 
                                            WHERE usuario_id = ? 
                                              AND tipo_pedido IN ('Extra', 'Avulso') 
                                              AND status_pagamento = 'Pendente' 
                                              AND status_entrega != 'Em separação' 
                                              AND fatura_id IS NULL
                                            FOR UPDATE";
                    $stmtElegiveis = $pdo->prepare($sqlPedidosElegiveis);
                    $stmtElegiveis->execute([$u_id]);
                    $pedidosElegiveis = $stmtElegiveis->fetchAll();

                    $valor_extras = 0.0;
                    $pedidoIds = [];
                    foreach ($pedidosElegiveis as $ped) {
                        $valor_extras += floatval($ped['valor_total']);
                        $pedidoIds[] = (int) $ped['id'];
                    }
                    $valor_extras = round($valor_extras, 2);

                    $subtotal = round($valor_mensalidade + $valor_extras, 2);
                    $desconto = round(min($saldo, $subtotal), 2);
                    $total = round($subtotal - $desconto, 2);

                    // Insere Fatura
                    $sqlInsert = "INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->execute([$u_id, $mes_referencia, $valor_mensalidade, $valor_extras, $desconto, $total]);
                    $faturaId = (int) $pdo->lastInsertId();

                    // Abate o saldo do usuário de forma condicional
                    if ($desconto > 0) {
                        $sqlUpdateSaldo = "UPDATE usuarios SET saldo_compensacao = saldo_compensacao - ? WHERE id = ? AND saldo_compensacao >= ?";
                        $stmtSaldo = $pdo->prepare($sqlUpdateSaldo);
                        $stmtSaldo->execute([$desconto, $u_id, $desconto]);
                        if ($stmtSaldo->rowCount() === 0) {
                            throw new Exception("Saldo de compensação insuficiente durante geração da fatura.");
                        }

                        // Registra débito na carteira
                        $pdo->prepare("INSERT INTO transacoes_financeiras (usuario_id, tipo, valor, motivo) VALUES (?, 'Debito', ?, ?)")
                            ->execute([$u_id, $desconto, "Abatimento na Fatura de $mes_referencia"]);
                    }

                    // Atualiza SOMENTE os pedidos previamente selecionados e vincula explicitamente à fatura
                    if (!empty($pedidoIds)) {
                        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
                        $sqlUpdatePedidos = "UPDATE pedidos 
                                             SET status_pagamento = 'Pago', 
                                                 fatura_id = ?, 
                                                 obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Faturado em ', ?, ']') 
                                             WHERE id IN ($placeholders) 
                                               AND usuario_id = ? 
                                               AND status_pagamento = 'Pendente' 
                                               AND fatura_id IS NULL";
                        $paramsUpdate = array_merge([$faturaId, $mes_referencia], $pedidoIds, [$u_id]);
                        $stmtUpdPed = $pdo->prepare($sqlUpdatePedidos);
                        $stmtUpdPed->execute($paramsUpdate);
                    }

                    $pdo->commit();
                    $faturas_geradas++;
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // Trata concorrência de chave única (usuario_id, mes_referencia) como operação já concluída
                    $sqlState = $ex->getCode();
                    $driverCode = $ex->errorInfo[1] ?? null;
                    if ($sqlState === '23000' || $driverCode === 1062) {
                        continue;
                    }
                    throw $ex;
                }
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                throw new Exception("Método não permitido. Utilize POST.");
            }
            Security::checkCSRF();
            if ($_SESSION['tipo_usuario'] !== 'cliente') throw new Exception("Apenas clientes.");
            
            $rawData = file_get_contents('php://input');
            $data = json_decode($rawData, true);
            $f_id = filter_var($data['fatura_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$f_id) throw new Exception("ID da fatura inválido.");

            $u_id = (int) $_SESSION['usuario_id'];
            error_log("PAGAR_FATURA iniciado: fatura_id={$f_id} usuario_id={$u_id}");

            require_once __DIR__ . '/app/MercadoPagoService.php';

            // 1. Bloqueia a fatura e persiste chave de idempotência ANTES da chamada externa
            $pdo->beginTransaction();
            $stmtF = $pdo->prepare(
                "SELECT id, usuario_id, mes_referencia, valor_mensalidade, valor_total, status, transacao_id, idempotency_key, mp_status 
                 FROM faturas_mensais 
                 WHERE id = ? AND usuario_id = ? 
                 FOR UPDATE"
            );
            $stmtF->execute([$f_id, $u_id]);
            $fatura = $stmtF->fetch();

            if (!$fatura) {
                $pdo->rollBack();
                throw new Exception("Fatura não encontrada.");
            }
            if ($fatura['status'] === 'Pago' || $fatura['mp_status'] === 'approved') {
                $pdo->rollBack();
                throw new Exception("Fatura não encontrada ou já paga.");
            }
            if ($fatura['status'] === 'Cancelado') {
                $pdo->rollBack();
                throw new Exception("Esta fatura está cancelada.");
            }
            if (in_array($fatura['mp_status'], ['processing', 'in_process'], true) || ($fatura['mp_status'] === 'pending' && !empty($fatura['transacao_id']))) {
                $pdo->rollBack();
                throw new Exception("Já existe um pagamento em processamento para esta fatura. Aguarde a confirmação.");
            }

            $valor_total = floatval($fatura['valor_total']);

            // Reutiliza chave persistida em caso de timeout/retry ou gera chave estável da operação
            $clientKey = trim((string) ($data['idempotency_key'] ?? ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '')));
            if (!empty($fatura['idempotency_key']) && ($fatura['mp_status'] === 'timeout' || $fatura['mp_status'] === null)) {
                $idempotencyKey = $fatura['idempotency_key'];
            } else {
                $contextoChave = $clientKey !== '' ? $clientKey : ($fatura['mes_referencia'] . '_' . number_format($valor_total, 2, '.', ''));
                $idempotencyKey = MercadoPagoService::buildIdempotencyKey('fatura', $f_id, $contextoChave);
            }

            $pdo->prepare("UPDATE faturas_mensais SET idempotency_key = ?, mp_status = 'processing' WHERE id = ? AND usuario_id = ?")
                ->execute([$idempotencyKey, $f_id, $u_id]);
            $pdo->commit();

            $mpResult = null;
            $mpData = $data['mercado_pago_data'] ?? null;

            if (is_array($mpData)) {
                $mpService = new MercadoPagoService();

                // Busca dados cadastrais do cliente no banco
                $stmtUser = $pdo->prepare("SELECT nome, email, cpf, telefone FROM usuarios WHERE id = ?");
                $stmtUser->execute([$u_id]);
                $usuario = $stmtUser->fetch();

                $nomeCompleto = trim($usuario['nome'] ?? '');
                $partesNome = explode(' ', $nomeCompleto, 2);
                $firstName = !empty($partesNome[0]) ? $partesNome[0] : 'Cliente';
                $lastName = !empty($partesNome[1]) ? $partesNome[1] : 'FazBem';
                $cpfLimpo = preg_replace('/\D/', '', $usuario['cpf'] ?? '');

                $payerEmail = !empty($mpData['payer']['email']) ? $mpData['payer']['email'] : ($usuario['email'] ?? '');

                $paymentPayload = [
                    "transaction_amount" => (float) $valor_total,
                    "description" => "Pagamento de Fatura Mensal #" . $f_id,
                    "external_reference" => "fatura:" . $f_id,
                    "metadata" => [
                        "recurso_tipo" => "fatura",
                        "recurso_id" => (int) $f_id,
                        "usuario_id" => (int) $u_id
                    ],
                    "payment_method_id" => $mpData['payment_method_id'] ?? null,
                    "payer" => [
                        "email" => $payerEmail,
                        "first_name" => $firstName,
                        "last_name" => $lastName
                    ]
                ];

                if (!empty($mpData['payer']['identification']['number'])) {
                    $paymentPayload['payer']['identification'] = $mpData['payer']['identification'];
                } elseif (!empty($cpfLimpo) && strlen($cpfLimpo) === 11) {
                    $paymentPayload['payer']['identification'] = [
                        "type" => "CPF",
                        "number" => $cpfLimpo
                    ];
                }

                if (isset($mpData['token'])) {
                    $paymentPayload["token"] = $mpData['token'];
                    $paymentPayload["installments"] = isset($mpData['installments']) ? (int) $mpData['installments'] : 1;
                    if (isset($mpData['issuer_id'])) {
                        $paymentPayload["issuer_id"] = $mpData['issuer_id'];
                    }
                }

                $mpResult = $mpService->createPayment($paymentPayload, $idempotencyKey);

                if ($mpResult['error_type'] === 'transport') {
                    // Preserva idempotency_key para reutilização segura em retry após falha de transporte/timeout
                    $pdo->prepare("UPDATE faturas_mensais SET mp_status = 'timeout' WHERE id = ? AND usuario_id = ?")
                        ->execute([$f_id, $u_id]);
                    throw new Exception($mpResult['message']);
                }

                if (!$mpResult['success']) {
                    $statusFalha = $mpResult['payment_status'] ?? 'rejected';
                    // Limpa a chave em caso de rejeição definitiva ou erro de validação para permitir nova tentativa com outro cartão/dados
                    $pdo->prepare("UPDATE faturas_mensais SET mp_status = ?, idempotency_key = NULL WHERE id = ? AND usuario_id = ?")
                        ->execute([$statusFalha, $f_id, $u_id]);
                    if ($mpResult['error_type'] === 'rejected') {
                        throw new Exception($mpResult['message']);
                    }
                    throw new Exception("Falha ao processar pagamento: " . $mpResult['message']);
                }
            }

            $mpPaymentId = null;
            $formaPagamento = 'Saldo/Crédito';
            $pixData = null;
            $statusFatura = 'Pago';
            $mpStatusFinal = 'approved';

            if (is_array($mpResult) && isset($mpResult['response'])) {
                $response = $mpResult['response'];
                $mpPaymentId = isset($response['id']) ? (string) $response['id'] : null;
                $formaPagamento = 'Mercado Pago - ' . ($mpData['payment_method_id'] ?? 'Online');
                $mpStatusFinal = $mpResult['payment_status'] ?? ($response['status'] ?? 'approved');

                if (in_array($mpStatusFinal, ['pending', 'in_process', 'authorized'], true)) {
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
                $pdo->prepare(
                    "UPDATE faturas_mensais 
                     SET status = ?, 
                         pago_em = IF(? = 'Pago', NOW(), pago_em), 
                         transacao_id = COALESCE(?, transacao_id), 
                         forma_pagamento = ?, 
                         mp_status = ? 
                     WHERE id = ? AND usuario_id = ?"
                )->execute([$statusFatura, $statusFatura, $mpPaymentId, $formaPagamento, $mpStatusFinal, $f_id, $u_id]);

                if ($statusFatura === 'Pago') {
                    $vMensalidadePaga = floatval($fatura['valor_mensalidade']);

                    // Apenas reativa assinaturas canceladas se o pagamento foi da mensalidade
                    if ($vMensalidadePaga > 0) {
                        $stmtSub = $pdo->prepare("SELECT status FROM assinaturas WHERE usuario_id = ? FOR UPDATE");
                        $stmtSub->execute([$u_id]);
                        $subStatus = $stmtSub->fetchColumn();
                        if ($subStatus === 'Cancelada') {
                            $pdo->prepare("UPDATE assinaturas SET status = 'Ativa' WHERE usuario_id = ?")
                                ->execute([$u_id]);
                        }
                    }
                }

                $pdo->commit();
            } catch (Exception $dbEx) {
                $pdo->rollBack();
                error_log("CRITICAL ERROR: Fatura processada no MP mas falhou ao atualizar localmente. fatura_id={$f_id}");
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
