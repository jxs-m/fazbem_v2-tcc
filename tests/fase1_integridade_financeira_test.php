<?php
/**
 * Suíte Automatizada de Testes — Fase 0 e Fase 1 (Integridade Financeira e Mercado Pago)
 * Executa em banco MySQL/MariaDB isolado (fazbem_v2_test) sem afetar dados de produção.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Env.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/MercadoPagoService.php';
require_once __DIR__ . '/../app/Models/Pedido.php';

$assertions = 0;
$failures = 0;

function assertTrue($condition, $message) {
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "  [FAIL] {$message}\n");
    } else {
        fwrite(STDOUT, "  [OK]   {$message}\n");
    }
}

function assertEquals($expected, $actual, $message) {
    $ok = ($expected == $actual);
    assertTrue($ok, $message . ($ok ? '' : " (esperado=" . var_export($expected, true) . ", obtido=" . var_export($actual, true) . ")"));
}

// 1. Conectar ao servidor de banco isolado de testes
$socket = getenv('TEST_DB_SOCKET') ?: '';
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') !== false ? getenv('TEST_DB_PASS') : '';
$testDbName = 'fazbem_v2_test';

if ($socket !== '') {
    $adminDsn = "mysql:unix_socket={$socket};charset=utf8mb4";
    $testDsn = "mysql:unix_socket={$socket};dbname={$testDbName};charset=utf8mb4";
} else {
    $portPart = $port !== '' ? ";port={$port}" : "";
    $adminDsn = "mysql:host={$host}{$portPart};charset=utf8mb4";
    $testDsn = "mysql:host={$host}{$portPart};dbname={$testDbName};charset=utf8mb4";
}

$pdo = new PDO($adminDsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
$pdo->exec("CREATE DATABASE `{$testDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$testDbName}`");

// Injetar conexão isolada no singleton Database
Database::setConexao($pdo);

fwrite(STDOUT, "\n=== 1. Fase 0: Schema e Reversibilidade de Migração (UP / DOWN / UP) ===\n");
$schemaSql = file_get_contents(__DIR__ . '/../db_schema.sql');
// Ajusta o schema para usar fazbem_v2_test em vez de fazbem_v2
$schemaSql = str_replace('`fazbem_v2`', "`{$testDbName}`", $schemaSql);
$pdo->exec($schemaSql);

$downSql = file_get_contents(__DIR__ . '/../migrations/001_fase1_integridade_financeira_down.sql');
$pdo->exec($downSql);

$colsAfterDown = $pdo->query("SHOW COLUMNS FROM faturas_mensais LIKE 'idempotency_key'")->fetchAll();
assertEquals(0, count($colsAfterDown), "Migração DOWN removeu coluna idempotency_key de faturas_mensais");

$upSql = file_get_contents(__DIR__ . '/../migrations/001_fase1_integridade_financeira_up.sql');
$pdo->exec($upSql);

$colsAfterUp = $pdo->query("SHOW COLUMNS FROM faturas_mensais LIKE 'idempotency_key'")->fetchAll();
assertEquals(1, count($colsAfterUp), "Migração UP recriou coluna idempotency_key em faturas_mensais");

$colsPedFatura = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'fatura_id'")->fetchAll();
assertEquals(1, count($colsPedFatura), "Migração UP recriou coluna fatura_id em pedidos");

$idxFaturaMes = $pdo->query("SHOW INDEX FROM faturas_mensais WHERE Key_name = 'uq_faturas_usuario_mes'")->fetchAll();
assertTrue(count($idxFaturaMes) >= 2, "Índice único uq_faturas_usuario_mes criado em faturas_mensais");

fwrite(STDOUT, "\n=== 2. Fase 1 (5.4): Carregamento explícito de .env e validação de credenciais ===\n");
$tmpDir = sys_get_temp_dir() . '/fazbem_env_test_' . getmypid();
@mkdir($tmpDir, 0700, true);
file_put_contents($tmpDir . '/.env', "TEST_EXPLICIT_VAR=from_dot_env\n");
file_put_contents($tmpDir . '/.env.php', "<?php\nTEST_EXPLICIT_VAR=from_dot_env_php\n");

unset($_ENV['TEST_EXPLICIT_VAR'], $_SERVER['TEST_EXPLICIT_VAR']);
putenv('TEST_EXPLICIT_VAR');
Env::load($tmpDir . '/.env', false);
assertEquals('from_dot_env', Env::get('TEST_EXPLICIT_VAR'), "Env::load prioriza .env explícito e não sobrescreve com .env.php");
@unlink($tmpDir . '/.env');
@unlink($tmpDir . '/.env.php');
@rmdir($tmpDir);

$_ENV['AMBIENTE'] = 'producao';
$_ENV['MERCADO_PAGO_ACCESS_TOKEN'] = 'TEST-token-invalido-em-producao';
$mpProd = new MercadoPagoService();
assertTrue(!$mpProd->hasValidCredentials(), "MercadoPagoService rejeita credencial TEST- em ambiente de produção");

$_ENV['AMBIENTE'] = 'test';
$_ENV['MERCADO_PAGO_ACCESS_TOKEN'] = 'TEST-valid-sandbox-token-123456';
$mpTest = new MercadoPagoService();
assertTrue($mpTest->hasValidCredentials(), "MercadoPagoService aceita credencial de sandbox em ambiente de teste");

fwrite(STDOUT, "\n=== 3. Fase 1 (5.1): Faturamento seletivo, vínculo fatura_id e unicidade ===\n");
// Criar cliente assinante com saldo de compensação R$ 25,00 e mensalidade R$ 100,00
$pdo->exec("INSERT INTO usuarios (id, nome, email, senha, telefone, cpf, endereco, tipo_usuario, saldo_compensacao)
            VALUES (10, 'Cliente Teste', 'cliente10@teste.com', 'hash', '55999999999', '52998224725', 'Rua A, 10', 'cliente', 25.00)");
$pdo->exec("INSERT INTO assinaturas (usuario_id, frequencia, valor_mensal, status)
            VALUES (10, 'Semanal', 100.00, 'Ativa')");
$pdo->exec("INSERT INTO produtos (id, nome, categoria, tipo_venda, preco, unidade, estoque_atual, peso_estimado_g)
            VALUES (100, 'Tomate Orgânico', 'Legumes', 'Inteiro', 10.00, 'kg', 50.000, 1000)");

// Pedido 101: Extra, Pendente, MAS 'Em separação' (NÃO elegível para fatura!)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (101, 10, 15.00, 'Pendente', 'Em separação', 'Extra', DATE_SUB(NOW(), INTERVAL 14 DAY))");
// Pedido 102: Extra, Pendente, 'Aguardando Entrega' (Elegível: R$ 20,00)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (102, 10, 20.00, 'Pendente', 'Aguardando Entrega', 'Extra', DATE_SUB(NOW(), INTERVAL 14 DAY))");
// Pedido 103: Avulso, Pendente, 'Entregue' (Elegível: R$ 10,00)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (103, 10, 10.00, 'Pendente', 'Entregue', 'Avulso', DATE_SUB(NOW(), INTERVAL 14 DAY))");

// Executar lógica de geração de faturas via helper em processo isolado simulando requisição admin
function runGerarFaturas(PDO $pdo, string $mes_referencia): int {
    $stmt = $pdo->query("SELECT a.usuario_id FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.status IN ('Ativa', 'Pausada')");
    $assinantes = $stmt->fetchAll();
    $geradas = 0;
    foreach ($assinantes as $ass) {
        $u_id = (int) $ass['usuario_id'];
        $pdo->beginTransaction();
        try {
            $stmtAss = $pdo->prepare("SELECT a.usuario_id, a.valor_mensal, u.saldo_compensacao FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.usuario_id = ? AND a.status IN ('Ativa', 'Pausada') FOR UPDATE");
            $stmtAss->execute([$u_id]);
            $dadosAss = $stmtAss->fetch();
            if (!$dadosAss) { $pdo->rollBack(); continue; }

            $check = $pdo->prepare("SELECT id FROM faturas_mensais WHERE usuario_id = ? AND mes_referencia = ? FOR UPDATE");
            $check->execute([$u_id, $mes_referencia]);
            if ($check->fetch()) { $pdo->rollBack(); continue; }

            $valor_mensalidade = round(floatval($dadosAss['valor_mensal']), 2);
            $saldo = max(0.0, round(floatval($dadosAss['saldo_compensacao']), 2));

            $stmtElegiveis = $pdo->prepare("SELECT id, valor_total FROM pedidos WHERE usuario_id = ? AND tipo_pedido IN ('Extra', 'Avulso') AND status_pagamento = 'Pendente' AND status_entrega != 'Em separação' AND fatura_id IS NULL FOR UPDATE");
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

            $stmtInsert = $pdo->prepare("INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$u_id, $mes_referencia, $valor_mensalidade, $valor_extras, $desconto, $total]);
            $faturaId = (int) $pdo->lastInsertId();

            if ($desconto > 0) {
                $pdo->prepare("UPDATE usuarios SET saldo_compensacao = saldo_compensacao - ? WHERE id = ? AND saldo_compensacao >= ?")->execute([$desconto, $u_id, $desconto]);
                $pdo->prepare("INSERT INTO transacoes_financeiras (usuario_id, tipo, valor, motivo) VALUES (?, 'Debito', ?, ?)")->execute([$u_id, $desconto, "Abatimento na Fatura de $mes_referencia"]);
            }

            if (!empty($pedidoIds)) {
                $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
                $paramsUpdate = array_merge([$faturaId, $mes_referencia], $pedidoIds, [$u_id]);
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Pago', fatura_id = ?, obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Faturado em ', ?, ']') WHERE id IN ($placeholders) AND usuario_id = ? AND status_pagamento = 'Pendente' AND fatura_id IS NULL")->execute($paramsUpdate);
            }

            $pdo->commit();
            $geradas++;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($ex->getCode() === '23000' || ($ex->errorInfo[1] ?? null) === 1062) continue;
            throw $ex;
        }
    }
    return $geradas;
}

$mesRef = '2026-10';
$geradas1 = runGerarFaturas($pdo, $mesRef);
assertEquals(1, $geradas1, "Primeira execução gerou exatamente 1 fatura para o assinante");

$fatura = $pdo->query("SELECT * FROM faturas_mensais WHERE usuario_id = 10 AND mes_referencia = '2026-10'")->fetch();
assertEquals(100.00, floatval($fatura['valor_mensalidade']), "Valor da mensalidade na fatura = 100.00");
assertEquals(30.00, floatval($fatura['valor_extras']), "Valor de extras inclui apenas os 2 pedidos pesados (20 + 10 = 30.00)");
assertEquals(25.00, floatval($fatura['valor_desconto_creditos']), "Desconto de créditos aplicado = 25.00");
assertEquals(105.00, floatval($fatura['valor_total']), "Valor total da fatura = 105.00");

$p101 = $pdo->query("SELECT status_pagamento, fatura_id FROM pedidos WHERE id = 101")->fetch();
assertEquals('Pendente', $p101['status_pagamento'], "Pedido 101 ('Em separação') permaneceu Pendente");
assertEquals(null, $p101['fatura_id'], "Pedido 101 ('Em separação') permaneceu com fatura_id NULL");

$p102 = $pdo->query("SELECT status_pagamento, fatura_id FROM pedidos WHERE id = 102")->fetch();
$p103 = $pdo->query("SELECT status_pagamento, fatura_id FROM pedidos WHERE id = 103")->fetch();
assertEquals('Pago', $p102['status_pagamento'], "Pedido 102 ('Aguardando Entrega') marcado como Pago");
assertEquals((int) $fatura['id'], (int) $p102['fatura_id'], "Pedido 102 vinculado explicitamente à fatura gerada");
assertEquals('Pago', $p103['status_pagamento'], "Pedido 103 ('Entregue') marcado como Pago");
assertEquals((int) $fatura['id'], (int) $p103['fatura_id'], "Pedido 103 vinculado explicitamente à fatura gerada");

$saldoRestante = floatval($pdo->query("SELECT saldo_compensacao FROM usuarios WHERE id = 10")->fetchColumn());
assertEquals(0.00, $saldoRestante, "Saldo de compensação abatido corretamente para 0.00");

// Repetição / Concorrência na geração de faturas
$geradas2 = runGerarFaturas($pdo, $mesRef);
assertEquals(0, $geradas2, "Segunda execução concorrente/repetida não gera fatura duplicada");

$duplicateBlocked = false;
try {
    $pdo->prepare("INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_total) VALUES (10, '2026-10', 100, 100)")->execute();
} catch (PDOException $e) {
    $duplicateBlocked = ($e->getCode() === '23000');
}
assertTrue($duplicateBlocked, "Constraint UNIQUE(usuario_id, mes_referencia) bloqueia duplicidade no banco");

fwrite(STDOUT, "\n=== 4. Fase 1 (5.2): Idempotência de Pagamento, Timeout e Reutilização de Chave ===\n");
$capturedHeaders = [];
$mockStep = 'timeout';

MercadoPagoService::setTransport(function ($method, $url, $headers, $payload) use (&$capturedHeaders, &$mockStep) {
    $idempHeader = null;
    foreach ($headers as $h) {
        if (stripos($h, 'X-Idempotency-Key:') === 0) {
            $idempHeader = trim(substr($h, strlen('X-Idempotency-Key:')));
        }
    }
    $capturedHeaders[] = [
        'method' => $method,
        'url' => $url,
        'idempotency_key' => $idempHeader,
        'external_reference' => $payload['external_reference'] ?? null
    ];

    if ($mockStep === 'timeout') {
        return ['http_code' => 0, 'body' => null, 'error' => 'Operation timed out after 25000 milliseconds'];
    }
    if ($mockStep === 'rejected') {
        return [
            'http_code' => 201,
            'body' => json_encode(['id' => 999001, 'status' => 'rejected', 'status_detail' => 'cc_rejected_bad_filled_security_code']),
            'error' => null
        ];
    }
    if ($mockStep === 'pending_pix') {
        return [
            'http_code' => 201,
            'body' => json_encode([
                'id' => 999002,
                'status' => 'pending',
                'payment_method_id' => 'pix',
                'external_reference' => $payload['external_reference'] ?? '',
                'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201PIX', 'qr_code_base64' => 'YmFzZTY0']]
            ]),
            'error' => null
        ];
    }
    return [
        'http_code' => 201,
        'body' => json_encode(['id' => 999003, 'status' => 'approved', 'external_reference' => $payload['external_reference'] ?? '']),
        'error' => null
    ];
});

$pedidoModel = new Pedido();
$key1 = MercadoPagoService::buildIdempotencyKey('pedido_u10', date('oW'), 'op_1');
$pedidoId = $pedidoModel->criarPedido(
    10,
    20.00,
    'Mercado Pago - pix',
    [['produto_id' => 100, 'quantidade' => 2.0, 'preco_unitario' => 10.00]],
    null,
    'Pendente',
    $key1,
    'processing',
    'Extra'
);

$pedRow = $pdo->query("SELECT idempotency_key, mp_status FROM pedidos WHERE id = {$pedidoId}")->fetch();
assertEquals($key1, $pedRow['idempotency_key'], "Chave de idempotência persistida no pedido ANTES da chamada ao Mercado Pago");
assertEquals('processing', $pedRow['mp_status'], "Estado mp_status='processing' persistido antes da chamada externa");

// Simular 1ª chamada com timeout de transporte
$mp = new MercadoPagoService();
$res1 = $mp->createPayment(['transaction_amount' => 20.00, 'external_reference' => "pedido:{$pedidoId}"], $pedRow['idempotency_key']);
assertEquals('transport', $res1['error_type'], "Falha de transporte diferenciada corretamente (error_type='transport')");
$pedidoModel->atualizarPagamentoMercadoPago($pedidoId, 10, 'Pendente', 'timeout', null, 'Mercado Pago - pix', false);

// Simular retry após timeout: recupera pedido existente e reutiliza a mesma chave
$pedRetry = $pedidoModel->buscarPedidoAtivoSemanaOuChave(10, $key1);
assertEquals('timeout', $pedRetry['mp_status'], "Pedido em estado 'timeout' elegível para retry idempotente");
assertEquals($key1, $pedRetry['idempotency_key'], "Chave idempotente preservada após timeout");

$mockStep = 'pending_pix';
$res2 = $mp->createPayment(['transaction_amount' => 20.00, 'external_reference' => "pedido:{$pedidoId}"], $pedRetry['idempotency_key']);
assertTrue($res2['success'], "Retry após timeout processado com sucesso");
assertEquals($capturedHeaders[0]['idempotency_key'], $capturedHeaders[1]['idempotency_key'], "Mesma chave X-Idempotency-Key reutilizada no retry após timeout");
assertEquals("pedido:{$pedidoId}", $capturedHeaders[1]['external_reference'], "external_reference tipada ('pedido:ID') enviada ao Mercado Pago");

$pedidoModel->atualizarPagamentoMercadoPago($pedidoId, 10, 'Pendente', 'pending', '999002', 'Mercado Pago - pix', false);

fwrite(STDOUT, "\n=== 5. Fase 1 (5.3): Reconciliação e Webhook (Pedido e Fatura, Duplicidade, Valor/Usuário, Estorno) ===\n");
$webhookPaymentData = [];
MercadoPagoService::setTransport(function ($method, $url, $headers, $payload) use (&$webhookPaymentData) {
    return [
        'http_code' => 200,
        'body' => json_encode($webhookPaymentData),
        'error' => null
    ];
});

function invokeWebhook(int $paymentId): array {
    $cmd = sprintf(
        'php -r %s',
        escapeshellarg('
            $_GET["topic"] = "payment";
            $_GET["id"] = ' . $paymentId . ';
            $_ENV["AMBIENTE"] = "test";
            $_ENV["MERCADO_PAGO_ACCESS_TOKEN"] = "TEST-valid-sandbox-token-123456";
            $_ENV["MP_WEBHOOK_SECRET"] = "";
            require "app/Database.php";
            $pdo = new PDO(' . var_export($GLOBALS['testDsn'], true) . ', ' . var_export($GLOBALS['user'], true) . ', ' . var_export($GLOBALS['pass'], true) . ', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            Database::setConexao($pdo);
            require "app/MercadoPagoService.php";
            MercadoPagoService::setTransport(function() {
                return ["http_code" => 200, "body" => file_get_contents(' . var_export($GLOBALS['mockWebhookFile'], true) . '), "error" => null];
            });
            ob_start();
            register_shutdown_function(function() {
                $out = ob_get_clean();
                $code = http_response_code();
                if (!$code) $code = 200;
                fwrite(STDOUT, json_encode(["code" => $code, "body" => json_decode($out, true)]));
            });
            include "api_mp_webhook.php";
        ')
    );
    $raw = shell_exec($cmd);
    return json_decode($raw, true) ?: ['code' => 500, 'body' => null];
}

$mockWebhookFile = sys_get_temp_dir() . '/mp_webhook_mock_' . getmypid() . '.json';

// 5.1 Reconciliar Pedido (approved) via external_reference tipada
file_put_contents($mockWebhookFile, json_encode([
    'id' => 999002,
    'status' => 'approved',
    'transaction_amount' => 20.00,
    'currency_id' => 'BRL',
    'payment_method_id' => 'pix',
    'external_reference' => "pedido:{$pedidoId}",
    'metadata' => ['usuario_id' => 10, 'recurso_tipo' => 'pedido', 'recurso_id' => $pedidoId]
]));

$wh1 = invokeWebhook(999002);
assertEquals(200, $wh1['code'], "Webhook retornou HTTP 200 ao aprovar pedido");
assertEquals(false, $wh1['body']['duplicate'] ?? true, "Primeira notificação do webhook não é marcada como duplicada");

$pedAfterWh = $pdo->query("SELECT status_pagamento, mp_status, transacao_id FROM pedidos WHERE id = {$pedidoId}")->fetch();
assertEquals('Pago', $pedAfterWh['status_pagamento'], "Webhook atualizou status_pagamento do pedido para 'Pago'");
assertEquals('approved', $pedAfterWh['mp_status'], "Webhook atualizou mp_status do pedido para 'approved'");
assertEquals('999002', $pedAfterWh['transacao_id'], "Webhook gravou transacao_id no pedido");

// 5.2 Reprocessamento idempotente de notificação duplicada
$wh2 = invokeWebhook(999002);
assertEquals(200, $wh2['code'], "Reprocessamento de webhook duplicado retornou HTTP 200");
assertEquals(true, $wh2['body']['duplicate'] ?? false, "Reprocessamento identificado como notificação duplicada");

// 5.3 Tentativa de conciliar fatura com valor divergente (deve recusar com HTTP 400)
$faturaId = (int) $fatura['id'];
file_put_contents($mockWebhookFile, json_encode([
    'id' => 999010,
    'status' => 'approved',
    'transaction_amount' => 10.00, // Fatura é 105.00!
    'currency_id' => 'BRL',
    'payment_method_id' => 'pix',
    'external_reference' => "fatura:{$faturaId}",
    'metadata' => ['usuario_id' => 10]
]));

$whValorErrado = invokeWebhook(999010);
assertEquals(400, $whValorErrado['code'], "Webhook rejeita pagamento com valor divergente da fatura (HTTP 400)");
$fatStatusCheck = $pdo->query("SELECT status FROM faturas_mensais WHERE id = {$faturaId}")->fetchColumn();
assertEquals('Pendente', $fatStatusCheck, "Fatura permanece Pendente após tentativa com valor divergente");

// 5.4 Conciliar fatura aprovada e reativar assinatura Cancelada
$pdo->exec("UPDATE assinaturas SET status = 'Cancelada' WHERE usuario_id = 10");
file_put_contents($mockWebhookFile, json_encode([
    'id' => 999011,
    'status' => 'approved',
    'transaction_amount' => 105.00,
    'currency_id' => 'BRL',
    'payment_method_id' => 'credit_card',
    'external_reference' => "fatura:{$faturaId}",
    'metadata' => ['usuario_id' => 10]
]));

$whFatOk = invokeWebhook(999011);
assertEquals(200, $whFatOk['code'], "Webhook aprovou fatura com valor e usuário válidos (HTTP 200)");
$fatAfterOk = $pdo->query("SELECT status, mp_status, transacao_id FROM faturas_mensais WHERE id = {$faturaId}")->fetch();
assertEquals('Pago', $fatAfterOk['status'], "Fatura marcada como Paga pelo webhook");
assertEquals('approved', $fatAfterOk['mp_status'], "mp_status da fatura atualizado para 'approved'");
$subAfterOk = $pdo->query("SELECT status FROM assinaturas WHERE usuario_id = 10")->fetchColumn();
assertEquals('Ativa', $subAfterOk, "Assinatura Cancelada foi reativada após confirmação de pagamento da mensalidade");

// 5.5 Conciliar estorno (refunded)
file_put_contents($mockWebhookFile, json_encode([
    'id' => 999011,
    'status' => 'refunded',
    'transaction_amount' => 105.00,
    'currency_id' => 'BRL',
    'payment_method_id' => 'credit_card',
    'external_reference' => "fatura:{$faturaId}",
    'metadata' => ['usuario_id' => 10]
]));

$whRefund = invokeWebhook(999011);
assertEquals(200, $whRefund['code'], "Webhook processou notificação de estorno (refunded)");
$fatAfterRefund = $pdo->query("SELECT status, mp_status, pago_em FROM faturas_mensais WHERE id = {$faturaId}")->fetch();
assertEquals('Cancelado', $fatAfterRefund['status'], "Fatura marcada como Cancelado após estorno");
assertEquals('refunded', $fatAfterRefund['mp_status'], "mp_status atualizado para 'refunded'");
assertEquals(null, $fatAfterRefund['pago_em'], "pago_em limpo após estorno");

@unlink($mockWebhookFile);
$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "Total de asserções: {$assertions} | Falhas: {$failures}\n");
fwrite(STDOUT, "============================================================\n");

exit($failures > 0 ? 1 : 0);
