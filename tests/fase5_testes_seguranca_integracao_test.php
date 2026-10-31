<?php
/**
 * Suíte Automatizada de Testes — Fase 5 (Testes Unitários, Integração e Segurança)
 * Executa em banco MySQL/MariaDB isolado (fazbem_v2_test) sem afetar dados de produção.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Env.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Validator.php';
require_once __DIR__ . '/../app/MercadoPagoService.php';
require_once __DIR__ . '/../app/Models/Pedido.php';
require_once __DIR__ . '/../app/Models/Produto.php';
require_once __DIR__ . '/../app/Models/Producao.php';
require_once __DIR__ . '/../app/Models/Assinatura.php';
require_once __DIR__ . '/../app/Models/Usuario.php';
require_once __DIR__ . '/../app/Models/Preferencia.php';

$assertions = 0;
$failures = 0;

function assertTrue(bool $condition, string $message): void {
    global $assertions, $failures;
    $assertions++;
    if ($condition) {
        fwrite(STDOUT, "  [OK]   {$message}\n");
    } else {
        $failures++;
        fwrite(STDERR, "  [FAIL] {$message}\n");
    }
}

function assertEquals($expected, $actual, string $message): void {
    $ok = ($expected == $actual);
    assertTrue($ok, $message . ($ok ? '' : " (esperado=" . var_export($expected, true) . ", obtido=" . var_export($actual, true) . ")"));
}

// 1. Configurar banco isolado de testes (fazbem_v2_test)
$socket = getenv('TEST_DB_SOCKET') ?: (getenv('DB_SOCKET') ?: '');
$host = getenv('TEST_DB_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1');
$port = getenv('TEST_DB_PORT') ?: (getenv('DB_PORT') ?: '');
$user = getenv('TEST_DB_USER') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('TEST_DB_PASS') !== false ? getenv('TEST_DB_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
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

$_ENV['AMBIENTE'] = 'test';
$_ENV['DB_NAME'] = $testDbName;
$_ENV['FORCE_CATALOGO_ABERTO'] = '1';
putenv("AMBIENTE=test");
putenv("DB_NAME={$testDbName}");
putenv("FORCE_CATALOGO_ABERTO=1");
if ($socket !== '') {
    $_ENV['DB_SOCKET'] = $socket;
    putenv("DB_SOCKET={$socket}");
}

Database::setConexao($pdo);

$schemaSql = str_replace('`fazbem_v2`', "`{$testDbName}`", file_get_contents(__DIR__ . '/../db_schema.sql'));
$seedsSql = str_replace('`fazbem_v2`', "`{$testDbName}`", file_get_contents(__DIR__ . '/../db_seeds.sql'));
$pdo->exec($schemaSql);
$pdo->exec($seedsSql);

/**
 * Helper para executar qualquer endpoint PHP em processo isolado,
 * suportando sessão customizada, headers, GET, POST JSON, POST form-data e $_FILES.
 */
function invokeEndpoint(string $scriptFile, array $options = []): array {
    global $socket, $host, $port, $user, $pass, $testDbName;

    $method = $options['method'] ?? 'GET';
    $session = $options['session'] ?? null;
    $get = $options['get'] ?? [];
    $post = $options['post'] ?? [];
    $files = $options['files'] ?? [];
    $headers = $options['headers'] ?? [];
    $jsonBody = array_key_exists('json', $options) ? json_encode($options['json']) : ($options['raw_body'] ?? '');
    $envOverrides = $options['env'] ?? [];
    $remoteAddr = $options['remote_addr'] ?? '127.0.0.1';
    $mpMockResponse = $options['mp_mock'] ?? null;

    $runner = tempnam(sys_get_temp_dir(), 'f5_runner_') . '.php';
    $targetPath = realpath(__DIR__ . '/../' . ltrim($scriptFile, '/'));

    $code = '<?php
    error_reporting(E_ALL);
    ini_set("display_errors", "0");

    class MockPhpInputStream {
        public $context;
        private $pos = 0;
        public static $body = "";
        public function stream_open($path, $mode, $options, &$opened_path) {
            return $path === "php://input";
        }
        public function stream_read($count) {
            $chunk = substr(self::$body, $this->pos, $count);
            $this->pos += strlen($chunk);
            return $chunk;
        }
        public function stream_eof() {
            return $this->pos >= strlen(self::$body);
        }
        public function stream_stat() {
            return [];
        }
    }
    MockPhpInputStream::$body = ' . var_export((string) $jsonBody, true) . ';
    @stream_wrapper_unregister("php");
    @stream_wrapper_register("php", "MockPhpInputStream");

    $_ENV["AMBIENTE"] = ' . var_export($envOverrides['AMBIENTE'] ?? 'test', true) . ';
    $_ENV["DB_HOST"] = ' . var_export($host, true) . ';
    $_ENV["DB_PORT"] = ' . var_export($port, true) . ';
    $_ENV["DB_USER"] = ' . var_export($user, true) . ';
    $_ENV["DB_PASS"] = ' . var_export($pass, true) . ';
    $_ENV["DB_NAME"] = ' . var_export($testDbName, true) . ';
    $_ENV["DB_SOCKET"] = ' . var_export($socket, true) . ';
    $_ENV["FORCE_CATALOGO_ABERTO"] = ' . var_export($envOverrides['FORCE_CATALOGO_ABERTO'] ?? '1', true) . ';
    $_ENV["MERCADO_PAGO_ACCESS_TOKEN"] = ' . var_export($envOverrides['MERCADO_PAGO_ACCESS_TOKEN'] ?? 'TEST-valid-sandbox-token-123456', true) . ';
    $_ENV["MP_WEBHOOK_SECRET"] = ' . var_export($envOverrides['MP_WEBHOOK_SECRET'] ?? '', true) . ';
    foreach ($_ENV as $k => $v) { putenv("$k=$v"); }

    $_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';
    $_SERVER["REMOTE_ADDR"] = ' . var_export($remoteAddr, true) . ';
    $_SERVER["SCRIPT_NAME"] = ' . var_export('/' . basename($scriptFile), true) . ';
    $_SERVER["HTTP_HOST"] = ' . var_export($headers['Host'] ?? 'localhost', true) . ';
    ';

    if (isset($headers['Origin'])) {
        $code .= '$_SERVER["HTTP_ORIGIN"] = ' . var_export($headers['Origin'], true) . ";\n";
    }
    if (isset($headers['X-CSRF-Token'])) {
        $code .= '$_SERVER["HTTP_X_CSRF_TOKEN"] = ' . var_export($headers['X-CSRF-Token'], true) . ";\n";
    }
    if (isset($headers['X-Idempotency-Key'])) {
        $code .= '$_SERVER["HTTP_X_IDEMPOTENCY_KEY"] = ' . var_export($headers['X-Idempotency-Key'], true) . ";\n";
    }

    $code .= '$_GET = ' . var_export($get, true) . ";\n";
    $code .= '$_POST = ' . var_export($post, true) . ";\n";
    $code .= '$_FILES = ' . var_export($files, true) . ";\n";

    if ($session !== null) {
        $code .= '
        session_start();
        $_SESSION = ' . var_export($session, true) . ';
        ';
    }

    if ($mpMockResponse !== null) {
        $code .= '
        require_once ' . var_export(realpath(__DIR__ . '/../app/MercadoPagoService.php'), true) . ';
        MercadoPagoService::setTransport(function($method, $url, $headers, $payload) {
            $mock = ' . var_export($mpMockResponse, true) . ';
            if (is_array($mock["body"] ?? null)) {
                if (!isset($mock["body"]["external_reference"]) && isset($payload["external_reference"])) {
                    $mock["body"]["external_reference"] = $payload["external_reference"];
                }
                if (!isset($mock["body"]["transaction_amount"]) && isset($payload["transaction_amount"])) {
                    $mock["body"]["transaction_amount"] = $payload["transaction_amount"];
                }
                $mock["body"] = json_encode($mock["body"]);
            }
            return [
                "http_code" => $mock["http_code"] ?? 201,
                "body" => $mock["body"] ?? "{}",
                "error" => $mock["error"] ?? null,
                "captured_payload" => $payload
            ];
        });
        ';
    }

    $code .= '
    register_shutdown_function(function() {
        $out = ob_get_contents();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        $code = http_response_code();
        if (!$code) { $code = 200; }
        $hdrs = headers_list();
        if (!empty($GLOBALS["__CORS_ALLOWED_ORIGIN"])) {
            $hdrs[] = "Access-Control-Allow-Origin: " . $GLOBALS["__CORS_ALLOWED_ORIGIN"];
        }
        $sess = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) ? $_SESSION : [];
        fwrite(STDOUT, json_encode([
            "http_code" => $code,
            "headers" => $hdrs,
            "body" => json_decode($out, true),
            "raw" => $out,
            "session" => $sess
        ]));
    });
    ob_start();
    require ' . var_export($targetPath, true) . ';
    ';

    file_put_contents($runner, $code);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open(['php', $runner], $descriptors, $pipes, realpath(__DIR__ . '/..'));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    @unlink($runner);

    return json_decode($stdout, true) ?: ['http_code' => 500, 'headers' => [], 'body' => null, 'raw' => $stdout, 'session' => []];
}

// ==============================================================================
// BLOCO 1: TESTES UNITÁRIOS (Seção 9.1 do Plano de Correção)
// ==============================================================================
fwrite(STDOUT, "\n=== 1. TESTES UNITÁRIOS: Preço, Quantidades, Unidades, Créditos, Estados e Assinatura ===\n");

$pedidoModel = new Pedido();

// 1.1 Cálculo de preço por unidade, grama e quilograma
$pdo->exec("INSERT INTO produtos (id, nome, categoria, tipo_venda, preco, unidade, estoque_atual, peso_estimado_g, ativo) VALUES
    (501, 'Alface Unidade', 'Verduras', 'Inteiro', 4.50, 'un', 50.000, 250, 1),
    (502, 'Tomate Quilo', 'Legumes', 'Fracionado', 12.00, 'kg', 50.000, 150, 1),
    (503, 'Manjericão Gramas', 'Verduras', 'Fracionado', 0.08, 'g', 5000.000, 50, 1),
    (504, 'Cogumelo Bandeja 200g', 'Outros', 'Fracionado', 16.00, '200g', 50.000, 200, 1)
    ON DUPLICATE KEY UPDATE preco = VALUES(preco), estoque_atual = VALUES(estoque_atual), ativo = 1");

// Preço por unidade inteira: 3 un * R$ 4,50 = R$ 13,50
$calcUn = $pedidoModel->processarCarrinho([
    ['id' => 501, 'input_qtd' => 3, 'tipo_compra' => 'Unidade']
]);
assertEquals(13.50, $calcUn['total_calculado'], "Preço por unidade: 3 un x R$ 4,50 = R$ 13,50");
assertEquals(3.000, $calcUn['itens_processados'][0]['quantidade'], "Quantidade calculada em unidades = 3.000");

// Preço por grama em produto cadastrado em kg: 750g de Tomate (R$ 12,00/kg) = 0.750 kg * 12 = R$ 9,00
$calcKgGramas = $pedidoModel->processarCarrinho([
    ['id' => 502, 'input_qtd' => 750, 'tipo_compra' => 'Gramas']
]);
assertEquals(9.00, $calcKgGramas['total_calculado'], "Preço por grama em produto 'kg': 750g a R$ 12,00/kg = R$ 9,00");
assertEquals(0.750, $calcKgGramas['itens_processados'][0]['quantidade'], "Conversão de 750g para unidade 'kg' = 0.750");

// Preço por unidade estimada em produto cadastrado em kg: 2 tomates (peso_estimado_g=150g -> 300g = 0.300 kg) * R$ 12,00 = R$ 3,60
$calcKgPorUn = $pedidoModel->processarCarrinho([
    ['id' => 502, 'input_qtd' => 2, 'tipo_compra' => 'Unidade']
]);
assertEquals(3.60, $calcKgPorUn['total_calculado'], "Preço por unidade estimada em produto 'kg': 2 un (300g) a R$ 12,00/kg = R$ 3,60");
assertEquals(0.300, $calcKgPorUn['itens_processados'][0]['quantidade'], "Conversão de 2 un (150g cada) para 'kg' = 0.300");

// Preço por grama em produto cadastrado em 'g': 150g * R$ 0,08/g = R$ 12,00
$calcGramas = $pedidoModel->processarCarrinho([
    ['id' => 503, 'input_qtd' => 150, 'tipo_compra' => 'Gramas']
]);
assertEquals(12.00, $calcGramas['total_calculado'], "Preço por grama em produto 'g': 150g a R$ 0,08/g = R$ 12,00");
assertEquals(150.000, $calcGramas['itens_processados'][0]['quantidade'], "Quantidade em 'g' = 150.000");

// Preço por grama em produto com unidade composta '200g': 300g = 1.500 bandeja * R$ 16,00 = R$ 24,00
$calc200g = $pedidoModel->processarCarrinho([
    ['id' => 504, 'input_qtd' => 300, 'tipo_compra' => 'Gramas']
]);
assertEquals(24.00, $calc200g['total_calculado'], "Preço para 300g em produto '200g' (1.5 x R$ 16,00) = R$ 24,00");
assertEquals(1.500, $calc200g['itens_processados'][0]['quantidade'], "Quantidade convertida para base '200g' = 1.500");

// 1.2 Producao::calcularEstoqueDecremento e Producao::formatarExibicao
$decKgFromG = Producao::calcularEstoqueDecremento(500, ['unidade' => 'kg', 'tipo_venda' => 'Fracionado'], 'g');
assertEquals(0.5, $decKgFromG, "Producao::calcularEstoqueDecremento converte 500g para 0.5 kg");
$decGFromKg = Producao::calcularEstoqueDecremento(1.5, ['unidade' => 'g', 'tipo_venda' => 'Fracionado'], 'kg');
assertEquals(1500.0, $decGFromKg, "Producao::calcularEstoqueDecremento converte 1.5kg para 1500 g");
assertEquals('1,25 kg', Producao::formatarExibicao('Tomate', 'kg', 'Fracionado', 1.250), "Producao::formatarExibicao formata 1.250 kg como '1,25 kg'");
assertEquals('2 maços', Producao::formatarExibicao('Rúcula', 'maço', 'Inteiro', 2), "Producao::formatarExibicao pluraliza '2 maços'");

// 1.3 Validador de CPF (Validator::validarCPF)
assertTrue(Validator::validarCPF('52998224725') === true, "Validator::validarCPF aceita CPF válido");
assertTrue(Validator::validarCPF('111.111.111-11') === false, "Validator::validarCPF rejeita CPF com dígitos repetidos");
assertTrue(Validator::validarCPF('52998224700') === false, "Validator::validarCPF rejeita CPF com dígito verificador inválido");

// 1.4 Regras de Assinatura, Créditos e Descontos (api_gerenciar_assinatura_v2.php)
$pdo->exec("DELETE FROM transacoes_financeiras WHERE usuario_id = 4");
$pdo->exec("DELETE FROM faturas_mensais WHERE usuario_id = 4");
$pdo->exec("UPDATE usuarios SET saldo_compensacao = 0.00 WHERE id = 4");
$pdo->exec("UPDATE assinaturas SET frequencia = 'Semanal', valor_mensal = 120.00, status = 'Ativa', ultima_pausa = NULL WHERE usuario_id = 4");

$sessCarlos = ['usuario_id' => 4, 'tipo_usuario' => 'cliente', 'csrf_token' => 'csrf_carlos_sub', 'session_version' => 1];

// Pausar assinatura Semanal (R$ 120,00 / 4 entregas = R$ 30,00 de crédito)
$resPausa = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'pausar']
]);
$saldoAposPausa = (float) $pdo->query("SELECT saldo_compensacao FROM usuarios WHERE id = 4")->fetchColumn();
$statusAssPausa = $pdo->query("SELECT status FROM assinaturas WHERE usuario_id = 4")->fetchColumn();
assertTrue($resPausa['http_code'] === 200 && ($resPausa['body']['success'] ?? false) === true, "Pausa de assinatura ativa concluída com sucesso");
assertEquals('Pausada', $statusAssPausa, "Status da assinatura alterado para 'Pausada'");
assertEquals(30.00, $saldoAposPausa, "Crédito de pausa semanal calculado corretamente (120 / 4 = R$ 30,00)");

// Bloqueio de reativação antes de 24h da última pausa
$resReativarCedo = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'reativar']
]);
assertEquals(400, $resReativarCedo['http_code'], "Reativação bloqueada antes de completar 24 horas da pausa");

// Simular 25 horas passadas, porém cliente gastou o saldo -> deve bloquear reativação
$pdo->exec("UPDATE assinaturas SET ultima_pausa = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE usuario_id = 4");
$pdo->exec("UPDATE usuarios SET saldo_compensacao = 10.00 WHERE id = 4");
$resReativarSemSaldo = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'reativar']
]);
assertEquals(400, $resReativarSemSaldo['http_code'], "Reativação bloqueada quando cliente utilizou o crédito da pausa e não tem saldo suficiente");

// Restaurar saldo de R$ 30,00 e reativar após 25h -> estorna crédito e volta para 'Ativa'
$pdo->exec("UPDATE usuarios SET saldo_compensacao = 30.00 WHERE id = 4");
$resReativarOk = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'reativar']
]);
$saldoAposReativar = (float) $pdo->query("SELECT saldo_compensacao FROM usuarios WHERE id = 4")->fetchColumn();
$statusAssReativada = $pdo->query("SELECT status FROM assinaturas WHERE usuario_id = 4")->fetchColumn();
assertTrue($resReativarOk['http_code'] === 200 && $statusAssReativada === 'Ativa' && $saldoAposReativar == 0.00, "Reativação após 24h debita o crédito da pausa (R$ 30,00 -> R$ 0,00) e ativa assinatura");

// Alterar plano para Quinzenal e pausar (R$ 120,00 / 2 entregas = R$ 60,00 de crédito)
$pdo->exec("UPDATE assinaturas SET ultima_pausa = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE usuario_id = 4");
$resAltPlano = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'alterar_plano', 'nova_frequencia' => 'Quinzenal']
]);
$resPausaQuinzenal = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'pausar']
]);
$saldoQuinzenal = (float) $pdo->query("SELECT saldo_compensacao FROM usuarios WHERE id = 4")->fetchColumn();
assertTrue($resAltPlano['http_code'] === 200 && $resPausaQuinzenal['http_code'] === 200, "Alteração para plano Quinzenal e pausa executadas com sucesso");
assertEquals(60.00, $saldoQuinzenal, "Crédito de pausa quinzenal calculado corretamente (120 / 2 = R$ 60,00)");

// Cancelar assinatura com fatura pendente que possui extras: remove mensalidade e mantém extras com desconto ajustado
$pdo->exec("INSERT INTO faturas_mensais (id, usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total, status)
            VALUES (5001, 4, '2026-10', 120.00, 40.00, 60.00, 100.00, 'Pendente')");
$resCancelarComExtras = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'cancelar']
]);
$fatPosCancel = $pdo->query("SELECT valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total FROM faturas_mensais WHERE id = 5001")->fetch();
assertTrue($resCancelarComExtras['http_code'] === 200, "Cancelamento de assinatura com fatura contendo extras concluído");
assertEquals(0.00, (float) $fatPosCancel['valor_mensalidade'], "Mensalidade zerada na fatura pendente após cancelamento da assinatura");
assertEquals(40.00, (float) $fatPosCancel['valor_desconto_creditos'], "Desconto de créditos limitado ao valor dos extras (min(60, 40) = 40.00)");
assertEquals(0.00, (float) $fatPosCancel['valor_total'], "Novo total da fatura recalculado corretamente após remoção da mensalidade");

// Deixar assinatura Ativa e sem faturas pendentes para os testes de integração
$pdo->exec("DELETE FROM faturas_mensais WHERE usuario_id = 4");
$pdo->exec("UPDATE assinaturas SET frequencia = 'Semanal', status = 'Ativa', ultima_pausa = NULL WHERE usuario_id = 4");
$pdo->exec("UPDATE usuarios SET saldo_compensacao = 0.00 WHERE id = 4");

// ==============================================================================
// BLOCO 2: TESTES DE INTEGRAÇÃO (Seção 9.2 do Plano de Correção)
// ==============================================================================
fwrite(STDOUT, "\n=== 2. TESTES DE INTEGRAÇÃO: Checkout Adulterado, Concorrência, Pagamentos, Pesagem Zero ===\n");

// 2.1 Checkout com total adulterado no cliente (api_checkout_v2.php)
$pdo->exec("DELETE FROM movimentacoes_estoque");
$pdo->exec("DELETE FROM historico_status_pedidos");
$pdo->exec("DELETE FROM itens_pedido");
$pdo->exec("DELETE FROM pedidos WHERE usuario_id = 4");
$pdo->exec("DELETE FROM rate_limits");
$pdo->exec("UPDATE produtos SET estoque_atual = 30.000, preco = 8.50, ativo = 1 WHERE id = 501");

// Cliente envia 2 unidades de R$ 8,50 (Total real = R$ 17,00), mas adultera 'total' para R$ 0,01!
$resCheckoutAdulterado = invokeEndpoint('api_checkout_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => [
        'itens' => [
            ['id' => 501, 'input_qtd' => 2, 'tipo_compra' => 'Unidade', 'preco' => 0.005, 'preco_estimado_calculado' => 0.01]
        ],
        'total' => 0.01,
        'pagamento' => 'Mercado Pago',
        'idempotency_key' => 'idem_adulterado_u4',
        'mercado_pago_data' => [
            'payment_method_id' => 'pix',
            'payer' => ['email' => 'carlos@email.com']
        ]
    ],
    'mp_mock' => [
        'http_code' => 201,
        'body' => [
            'id' => 777001,
            'status' => 'approved',
            'payment_method_id' => 'pix'
        ]
    ]
]);

assertTrue($resCheckoutAdulterado['http_code'] === 200 && ($resCheckoutAdulterado['body']['success'] ?? false) === true, "Checkout concluído ignorando preço adulterado pelo cliente");
$pedAdulteradoId = (int) ($resCheckoutAdulterado['body']['pedido_id'] ?? 0);
$pedAdulteradoRow = $pdo->query("SELECT valor_total, status_pagamento, mp_status FROM pedidos WHERE id = {$pedAdulteradoId}")->fetch();
assertEquals(17.00, (float) $pedAdulteradoRow['valor_total'], "Servidor recalculou e persistiu o valor real (R$ 17,00) em vez do valor adulterado (R$ 0,01)");
assertEquals('Pago', $pedAdulteradoRow['status_pagamento'], "Pagamento aprovado no checkout marcou pedido como 'Pago'");

// 2.2 Checkout com pagamento RECUSADO (rejected) -> cancela pedido, estorna estoque e libera limite semanal
$pdo->exec("DELETE FROM movimentacoes_estoque");
$pdo->exec("DELETE FROM historico_status_pedidos");
$pdo->exec("DELETE FROM itens_pedido");
$pdo->exec("DELETE FROM pedidos WHERE usuario_id = 4");
$pdo->exec("UPDATE produtos SET estoque_atual = 10.000 WHERE id = 501");

$resCheckoutRecusado = invokeEndpoint('api_checkout_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => [
        'itens' => [['id' => 501, 'input_qtd' => 3, 'tipo_compra' => 'Unidade']],
        'total' => 25.50,
        'pagamento' => 'Cartão de Crédito',
        'idempotency_key' => 'idem_recusado_u4',
        'mercado_pago_data' => [
            'payment_method_id' => 'master',
            'token' => 'tok_card_rejected',
            'installments' => 1,
            'payer' => ['email' => 'carlos@email.com']
        ]
    ],
    'mp_mock' => [
        'http_code' => 201,
        'body' => [
            'id' => 777002,
            'status' => 'rejected',
            'status_detail' => 'cc_rejected_insufficient_amount'
        ]
    ]
]);

$estAposRecusa = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 501")->fetchColumn();
$pedRecusado = $pdo->query("SELECT status_pagamento, mp_status, estoque_reservado, semana_ativa_chave FROM pedidos WHERE usuario_id = 4 ORDER BY id DESC LIMIT 1")->fetch();
assertEquals(400, $resCheckoutRecusado['http_code'], "Checkout com pagamento recusado retorna HTTP 400");
assertEquals('Cancelado', $pedRecusado['status_pagamento'], "Pedido recusado tem status_pagamento = 'Cancelado'");
assertEquals(10.000, $estAposRecusa, "Estoque reservado é imediatamente estornado quando o pagamento é recusado (10.000)");
assertEquals(null, $pedRecusado['semana_ativa_chave'], "Chave semanal liberada após recusa para permitir nova tentativa");

// 2.3 Checkout duplicado em paralelo (duas requisições simultâneas para o mesmo cliente)
$pdo->exec("DELETE FROM movimentacoes_estoque");
$pdo->exec("DELETE FROM historico_status_pedidos");
$pdo->exec("DELETE FROM itens_pedido");
$pdo->exec("DELETE FROM pedidos WHERE usuario_id = 4");
$pdo->exec("UPDATE produtos SET estoque_atual = 20.000 WHERE id = 501");

function launchParallelRequest(string $scriptFile, array $session, array $jsonPayload, string $csrfToken): array {
    global $socket, $host, $port, $user, $pass, $testDbName;
    $runner = tempnam(sys_get_temp_dir(), 'f5_par_') . '.php';
    $targetPath = realpath(__DIR__ . '/../' . ltrim($scriptFile, '/'));
    $rawJson = json_encode($jsonPayload);
    $code = '<?php
    class MockPhpInputStreamPar {
        public $context;
        private $pos = 0;
        public static $body = "";
        public function stream_open($path, $mode, $options, &$opened_path) { return $path === "php://input"; }
        public function stream_read($count) {
            $chunk = substr(self::$body, $this->pos, $count);
            $this->pos += strlen($chunk);
            return $chunk;
        }
        public function stream_eof() { return $this->pos >= strlen(self::$body); }
        public function stream_stat() { return []; }
    }
    MockPhpInputStreamPar::$body = ' . var_export((string) $rawJson, true) . ';
    @stream_wrapper_unregister("php");
    @stream_wrapper_register("php", "MockPhpInputStreamPar");

    $_ENV["AMBIENTE"] = "test";
    $_ENV["DB_HOST"] = ' . var_export($host, true) . ';
    $_ENV["DB_PORT"] = ' . var_export($port, true) . ';
    $_ENV["DB_USER"] = ' . var_export($user, true) . ';
    $_ENV["DB_PASS"] = ' . var_export($pass, true) . ';
    $_ENV["DB_NAME"] = ' . var_export($testDbName, true) . ';
    $_ENV["DB_SOCKET"] = ' . var_export($socket, true) . ';
    $_ENV["FORCE_CATALOGO_ABERTO"] = "1";
    foreach ($_ENV as $k => $v) { putenv("$k=$v"); }
    $_SERVER["REQUEST_METHOD"] = "POST";
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    $_SERVER["SCRIPT_NAME"] = ' . var_export('/' . basename($scriptFile), true) . ';
    $_SERVER["HTTP_X_CSRF_TOKEN"] = ' . var_export($csrfToken, true) . ';
    $_GET = ' . var_export($jsonPayload['_get'] ?? [], true) . ';
    session_start();
    $_SESSION = ' . var_export($session, true) . ';
    register_shutdown_function(function() {
        $out = ob_get_contents();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        fwrite(STDOUT, json_encode(["http_code" => http_response_code() ?: 200, "body" => json_decode($out, true)]));
    });
    ob_start();
    require ' . var_export($targetPath, true) . ';
    ';
    file_put_contents($runner, $code);
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(['php', $runner], $descriptors, $pipes, realpath(__DIR__ . '/..'));
    fclose($pipes[0]);
    return ['proc' => $proc, 'pipes' => $pipes, 'runner' => $runner];
}

function collectParallelRequest(array $handle): array {
    $stdout = stream_get_contents($handle['pipes'][1]);
    fclose($handle['pipes'][1]);
    fclose($handle['pipes'][2]);
    proc_close($handle['proc']);
    @unlink($handle['runner']);
    return json_decode($stdout, true) ?: ['http_code' => 500, 'body' => null];
}

$payloadParalelo1 = [
    'itens' => [['id' => 501, 'input_qtd' => 4, 'tipo_compra' => 'Unidade']],
    'total' => 34.00,
    'pagamento' => 'PIX',
    'idempotency_key' => 'idem_par_1'
];
$payloadParalelo2 = [
    'itens' => [['id' => 501, 'input_qtd' => 4, 'tipo_compra' => 'Unidade']],
    'total' => 34.00,
    'pagamento' => 'PIX',
    'idempotency_key' => 'idem_par_2'
];

$p1 = launchParallelRequest('api_checkout_v2.php', $sessCarlos, $payloadParalelo1, 'csrf_carlos_sub');
$p2 = launchParallelRequest('api_checkout_v2.php', $sessCarlos, $payloadParalelo2, 'csrf_carlos_sub');
$resPar1 = collectParallelRequest($p1);
$resPar2 = collectParallelRequest($p2);

$codigosPar = [$resPar1['http_code'], $resPar2['http_code']];
sort($codigosPar);
$qtdPedidosAtivos = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE usuario_id = 4 AND status_pagamento != 'Cancelado'")->fetchColumn();
$estAposParalelo = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 501")->fetchColumn();

assertEquals([200, 400], $codigosPar, "Checkout duplicado em paralelo: exatamente 1 requisição aprova (200) e 1 é bloqueada (400)");
assertEquals(1, $qtdPedidosAtivos, "Apenas 1 pedido ativo foi criado para o cliente na semana sob concorrência");
assertEquals(16.000, $estAposParalelo, "Estoque decrementado apenas uma vez (20.000 -> 16.000) sob concorrência");

// 2.4 Pesagem com peso ZERO (falta total sem substituição) via api_separacao_v2.php
$pedAtivoId = (int) $pdo->query("SELECT id FROM pedidos WHERE usuario_id = 4 AND status_pagamento != 'Cancelado' LIMIT 1")->fetchColumn();
$itemAtivoId = (int) $pdo->query("SELECT id FROM itens_pedido WHERE pedido_id = {$pedAtivoId} LIMIT 1")->fetchColumn();

$sessSeparador = ['usuario_id' => 1, 'tipo_usuario' => 'admin', 'csrf_token' => 'csrf_sep_test', 'session_version' => 1];
$resPesagemZero = invokeEndpoint('api_separacao_v2.php', [
    'method' => 'POST',
    'session' => $sessSeparador,
    'headers' => ['X-CSRF-Token' => 'csrf_sep_test'],
    'json' => [
        'pedido_id' => $pedAtivoId,
        'itens' => [
            ['item_id' => $itemAtivoId, 'quantidade_real' => 0]
        ],
        'motivo' => 'Produto em falta total na bancada'
    ]
]);

$estAposPesoZero = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 501")->fetchColumn();
$pedAposPesoZero = $pdo->query("SELECT valor_total, status_entrega FROM pedidos WHERE id = {$pedAtivoId}")->fetch();
assertTrue($resPesagemZero['http_code'] === 200 && ($resPesagemZero['body']['success'] ?? false) === true, "Pesagem com quantidade zero (falta total) processada em api_separacao_v2.php");
assertEquals(20.000, $estAposPesoZero, "Pesagem zero estorna integralmente as 4 unidades reservadas ao estoque (16.000 -> 20.000)");
assertEquals(0.00, (float) $pedAposPesoZero['valor_total'], "Valor total do pedido extra atualizado para R$ 0,00 quando único item teve peso zero");
assertEquals('Aguardando Entrega', $pedAposPesoZero['status_entrega'], "Pedido transicionou para 'Aguardando Entrega'");

// 2.5 Geração concorrente de fatura via endpoint api_faturamento_v2.php?acao=gerar_faturas
$pdo->exec("DELETE FROM faturas_mensais");
$pdo->exec("UPDATE usuarios SET saldo_compensacao = 20.00 WHERE id = 4");
$sessAdmin = ['usuario_id' => 1, 'tipo_usuario' => 'admin', 'csrf_token' => 'csrf_admin_fat', 'session_version' => 1];

$f1 = launchParallelRequest('api_faturamento_v2.php', $sessAdmin, ['_get' => ['acao' => 'gerar_faturas']], 'csrf_admin_fat');
$f2 = launchParallelRequest('api_faturamento_v2.php', $sessAdmin, ['_get' => ['acao' => 'gerar_faturas']], 'csrf_admin_fat');
$resFat1 = collectParallelRequest($f1);
$resFat2 = collectParallelRequest($f2);

$totalFaturasCarlos = (int) $pdo->query("SELECT COUNT(*) FROM faturas_mensais WHERE usuario_id = 4")->fetchColumn();
$saldoCarlosPosFat = (float) $pdo->query("SELECT saldo_compensacao FROM usuarios WHERE id = 4")->fetchColumn();
assertTrue($resFat1['http_code'] === 200 && $resFat2['http_code'] === 200, "Ambas as chamadas paralelas de geração de faturas responderam HTTP 200 sem erro fatal");
assertEquals(1, $totalFaturasCarlos, "Geração concorrente de faturas criou exatamente 1 fatura para o cliente no mês");
assertEquals(0.00, $saldoCarlosPosFat, "Saldo de compensação foi abatido uma única vez sob concorrência");

// ==============================================================================
// BLOCO 3: TESTES DE SEGURANÇA (Seção 9.3 do Plano de Correção)
// ==============================================================================
fwrite(STDOUT, "\n=== 3. TESTES DE SEGURANÇA: CSRF, Papéis, IDOR, XSS, Upload, Rate Limit, CORS e Sessão ===\n");

// 3.1 CSRF ausente, inválido e reutilizado (pós-login / pós-logout)
$resCsrfAusente = invokeEndpoint('api_perfil_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => [], // Sem X-CSRF-Token
    'json' => ['nome' => 'Carlos', 'telefone' => '55999999999', 'endereco' => 'Rua A']
]);
assertEquals(403, $resCsrfAusente['http_code'], "CSRF ausente bloqueado com HTTP 403");

$resCsrfInvalido = invokeEndpoint('api_perfil_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'token_forjado_invalido_999'],
    'json' => ['nome' => 'Carlos', 'telefone' => '55999999999', 'endereco' => 'Rua A']
]);
assertEquals(403, $resCsrfInvalido['http_code'], "CSRF inválido bloqueado com HTTP 403");

// Rotação de CSRF no login impede reutilização do token anterior à autenticação
$tokenPreLogin = 'token_antigo_pre_login_123456';
$pdo->exec("UPDATE usuarios SET senha = " . $pdo->quote(password_hash('SenhaCliente123', PASSWORD_DEFAULT)) . " WHERE id = 4");
$resLogin = invokeEndpoint('api_login_v2.php', [
    'method' => 'POST',
    'session' => ['csrf_token' => $tokenPreLogin],
    'headers' => ['X-CSRF-Token' => $tokenPreLogin],
    'json' => ['email' => 'carlos@email.com', 'senha' => 'SenhaCliente123']
]);
$novoTokenSessao = $resLogin['session']['csrf_token'] ?? '';
assertTrue($resLogin['http_code'] === 200 && ($resLogin['body']['success'] ?? false) === true, "Login realizado com sucesso");
assertTrue($novoTokenSessao !== '' && $novoTokenSessao !== $tokenPreLogin, "Login rotaciona o token CSRF da sessão");

// Tentar reutilizar o $tokenPreLogin na nova sessão autenticada deve falhar com 403
$resCsrfReutilizado = invokeEndpoint('api_perfil_v2.php', [
    'method' => 'POST',
    'session' => $resLogin['session'],
    'headers' => ['X-CSRF-Token' => $tokenPreLogin],
    'json' => ['nome' => 'Carlos', 'telefone' => '55999999999', 'endereco' => 'Rua A']
]);
assertEquals(403, $resCsrfReutilizado['http_code'], "Token CSRF antigo (reutilizado após rotação de login) é rejeitado com HTTP 403");

// Logout exige CSRF válido e destrói a sessão impedindo reuso posterior
$resLogoutSemCsrf = invokeEndpoint('logout.php', [
    'method' => 'POST',
    'session' => $resLogin['session'],
    'headers' => []
]);
assertEquals(403, $resLogoutSemCsrf['http_code'], "Logout via POST sem token CSRF é bloqueado com HTTP 403");

$resLogoutOk = invokeEndpoint('logout.php', [
    'method' => 'POST',
    'session' => $resLogin['session'],
    'headers' => ['X-CSRF-Token' => $novoTokenSessao]
]);
assertTrue($resLogoutOk['http_code'] === 200 && empty($resLogoutOk['session']), "Logout válido destrói os dados da sessão");

$resPosLogout = invokeEndpoint('api_perfil_v2.php', [
    'method' => 'POST',
    'session' => $resLogoutOk['session'],
    'headers' => ['X-CSRF-Token' => $novoTokenSessao],
    'json' => ['nome' => 'Carlos', 'telefone' => '55999999999', 'endereco' => 'Rua A']
]);
assertEquals(403, $resPosLogout['http_code'], "Reutilização de token CSRF após logout é bloqueada com HTTP 403");

// 3.2 Acesso cruzado entre papéis (cliente, separador, entregador, admin)
$sessEntregador = ['usuario_id' => 2, 'tipo_usuario' => 'entregador', 'csrf_token' => 'csrf_ent', 'session_version' => 1];
$sessApenasSeparador = ['usuario_id' => 3, 'tipo_usuario' => 'separador', 'csrf_token' => 'csrf_sep', 'session_version' => 1];

// Cliente tentando acessar painel admin, separação e logística
assertEquals(403, invokeEndpoint('api_admin_pedidos_v2.php', ['session' => $sessCarlos])['http_code'], "Cliente bloqueado em api_admin_pedidos_v2.php (403)");
assertEquals(403, invokeEndpoint('api_admin_produtos_v2.php', ['session' => $sessCarlos])['http_code'], "Cliente bloqueado em api_admin_produtos_v2.php (403)");
assertEquals(403, invokeEndpoint('api_admin_dashboard_v2.php', ['session' => $sessCarlos])['http_code'], "Cliente bloqueado em api_admin_dashboard_v2.php (403)");
assertEquals(403, invokeEndpoint('api_separacao_v2.php', ['session' => $sessCarlos])['http_code'], "Cliente bloqueado em api_separacao_v2.php (403)");
assertEquals(403, invokeEndpoint('api_logistica_v2.php', ['session' => $sessCarlos])['http_code'], "Cliente bloqueado em api_logistica_v2.php (403)");

// Entregador tentando acessar separação, admin e área exclusiva de cliente
assertEquals(403, invokeEndpoint('api_separacao_v2.php', ['session' => $sessEntregador])['http_code'], "Entregador bloqueado em api_separacao_v2.php (403)");
assertEquals(403, invokeEndpoint('api_admin_clientes_v2.php', ['session' => $sessEntregador])['http_code'], "Entregador bloqueado em api_admin_clientes_v2.php (403)");
assertEquals(403, invokeEndpoint('api_minha_assinatura_v2.php', ['session' => $sessEntregador])['http_code'], "Entregador bloqueado em api_minha_assinatura_v2.php (403)");

// Separador tentando acessar logística, faturamento admin e checkout de cliente
assertEquals(403, invokeEndpoint('api_logistica_v2.php', ['session' => $sessApenasSeparador])['http_code'], "Separador bloqueado em api_logistica_v2.php (403)");
assertEquals(403, invokeEndpoint('api_faturamento_v2.php', ['session' => $sessApenasSeparador, 'get' => ['acao' => 'listar_faturas_admin']])['http_code'], "Separador bloqueado em listar_faturas_admin (403)");
assertEquals(403, invokeEndpoint('api_meus_pedidos_v2.php', ['session' => $sessApenasSeparador])['http_code'], "Separador bloqueado em api_meus_pedidos_v2.php (403)");

// 3.3 IDOR em pedidos, faturas, preferências, produtos e rotas
// Criar pedido e fatura para Cliente B (usuario_id = 2) e tentar acessar com Cliente A (usuario_id = 4, Carlos)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido)
            VALUES (8801, 2, 45.00, 'Pago', 'Entregue', 'Extra')
            ON DUPLICATE KEY UPDATE usuario_id = 2");
$pdo->exec("INSERT INTO faturas_mensais (id, usuario_id, mes_referencia, valor_mensalidade, valor_total, status)
            VALUES (8802, 2, '2026-09', 100.00, 100.00, 'Pendente')
            ON DUPLICATE KEY UPDATE usuario_id = 2, status = 'Pendente'");
$pdo->exec("INSERT INTO preferencias (id, usuario_id, tipo, descricao)
            VALUES (8803, 2, 'Observação', 'Preferência privada da Maria')
            ON DUPLICATE KEY UPDATE usuario_id = 2");

// IDOR 1: Cliente A tentando visualizar comprovante de pedido do Cliente B
$resIdorComprovantePed = invokeEndpoint('comprovante.php', [
    'session' => $sessCarlos,
    'get' => ['tipo' => 'pedido', 'id' => 8801]
]);
assertEquals(403, $resIdorComprovantePed['http_code'], "IDOR bloqueado: Cliente A não consegue ver comprovante de pedido do Cliente B (403)");

// IDOR 2: Cliente A tentando visualizar comprovante de fatura do Cliente B
$resIdorComprovanteFat = invokeEndpoint('comprovante.php', [
    'session' => $sessCarlos,
    'get' => ['tipo' => 'fatura', 'id' => 8802]
]);
assertEquals(403, $resIdorComprovanteFat['http_code'], "IDOR bloqueado: Cliente A não consegue ver comprovante de fatura do Cliente B (403)");

// IDOR 3: Cliente A tentando pagar fatura do Cliente B
$resIdorPagarFat = invokeEndpoint('api_faturamento_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'get' => ['acao' => 'pagar_fatura'],
    'json' => ['fatura_id' => 8802]
]);
assertEquals(400, $resIdorPagarFat['http_code'], "IDOR bloqueado: Cliente A não consegue pagar/acessar fatura pertencente ao Cliente B");

// IDOR 4: Cliente A tentando remover preferência do Cliente B
$resIdorPref = invokeEndpoint('api_gerenciar_assinatura_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => ['acao' => 'remover_preferencia', 'pref_id' => 8803]
]);
$prefMariaAindaExiste = (int) $pdo->query("SELECT COUNT(*) FROM preferencias WHERE id = 8803 AND usuario_id = 2")->fetchColumn();
assertEquals(1, $prefMariaAindaExiste, "IDOR bloqueado: Cliente A não consegue excluir preferência pertencente ao Cliente B");

// IDOR 5: Separador tentando pesar item_id de outro pedido (8801) dentro do pedido atual ($pedAtivoId)
$pdo->exec("UPDATE pedidos SET status_entrega = 'Em separação' WHERE id = {$pedAtivoId}");
$pdo->exec("INSERT INTO itens_pedido (id, pedido_id, produto_id, quantidade, preco_unitario) VALUES (8899, 8801, 501, 1.000, 4.50) ON DUPLICATE KEY UPDATE pedido_id = 8801");
$resIdorItemPesagem = invokeEndpoint('api_separacao_v2.php', [
    'method' => 'POST',
    'session' => $sessSeparador,
    'headers' => ['X-CSRF-Token' => 'csrf_sep_test'],
    'json' => [
        'pedido_id' => $pedAtivoId,
        'itens' => [
            ['item_id' => 8899, 'quantidade_real' => 1.0]
        ]
    ]
]);
assertEquals(400, $resIdorItemPesagem['http_code'], "IDOR bloqueado: separação rejeita item_id que pertence a outro pedido (400)");

// 3.4 XSS em campos de usuário e produto (Sanitização na entrada + Escape na saída)
$resXssPerfil = invokeEndpoint('api_perfil_v2.php', [
    'method' => 'POST',
    'session' => $sessCarlos,
    'headers' => ['X-CSRF-Token' => 'csrf_carlos_sub'],
    'json' => [
        'nome' => 'Carlos <script>alert("xss")</script> Silva',
        'telefone' => '55988887777',
        'endereco' => 'Rua <img src=x onerror=alert(1)> Flores, 10',
        'referencia' => 'Perto do <b>Mercado</b>'
    ]
]);
$userCarlosDb = $pdo->query("SELECT nome, endereco, ponto_referencia FROM usuarios WHERE id = 4")->fetch();
assertTrue(
    $resXssPerfil['http_code'] === 200
    && strpos($userCarlosDb['nome'], '<script>') === false
    && strpos($userCarlosDb['endereco'], '<img') === false
    && strpos($userCarlosDb['ponto_referencia'], '<b>') === false,
    "XSS sanitizado nos campos de perfil do usuário (tags <script>, <img> e HTML removidas antes de gravar)"
);

$resXssProduto = invokeEndpoint('api_admin_produtos_v2.php', [
    'method' => 'POST',
    'session' => $sessAdmin,
    'headers' => ['X-CSRF-Token' => 'csrf_admin_fat'],
    'post' => [
        'id' => 501,
        'nome' => 'Alface <script>evil()</script> Crespa',
        'categoria' => 'Verduras<svg onload=alert(1)>',
        'preco' => '4.50',
        'unidade' => 'un',
        'estoque' => '20',
        'tipo_venda' => 'Inteiro'
    ]
]);
$prod501Db = $pdo->query("SELECT nome, categoria FROM produtos WHERE id = 501")->fetch();
assertTrue(
    $resXssProduto['http_code'] === 200
    && strpos($prod501Db['nome'], '<script>') === false
    && strpos($prod501Db['categoria'], '<svg') === false,
    "XSS sanitizado nos campos de produto (tags <script> e <svg> removidas antes de gravar)"
);

// Verificar escape HTML em comprovante.php mesmo se houver caracteres especiais no banco
$pdo->exec("UPDATE usuarios SET endereco = 'Rua \"Teste\" & <Esquina>' WHERE id = 4");
$resComprovanteXss = invokeEndpoint('comprovante.php', [
    'session' => $sessCarlos,
    'get' => ['tipo' => 'pedido', 'id' => $pedAtivoId]
]);
assertTrue(
    $resComprovanteXss['http_code'] === 200
    && strpos($resComprovanteXss['raw'], '&lt;Esquina&gt;') !== false
    && strpos($resComprovanteXss['raw'], '<Esquina>') === false,
    "comprovante.php escapa caracteres HTML especiais (&lt;, &gt;, &quot;, &amp;) prevenindo Stored XSS"
);

// 3.5 Upload inválido em api_admin_produtos_v2.php (MIME enganoso, imagem corrompida, arquivo > 5MB)
$fakePhpFile = tempnam(sys_get_temp_dir(), 'fake_img_') . '.jpg';
file_put_contents($fakePhpFile, "<?php system(\$_GET['cmd']); ?>");

$resUploadPhp = invokeEndpoint('api_admin_produtos_v2.php', [
    'method' => 'POST',
    'session' => $sessAdmin,
    'headers' => ['X-CSRF-Token' => 'csrf_admin_fat'],
    'post' => [
        'nome' => 'Produto Upload Malicioso',
        'categoria' => 'Frutas',
        'preco' => '10.00',
        'unidade' => 'un',
        'estoque' => '5'
    ],
    'files' => [
        'imagem' => [
            'name' => 'exploit.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $fakePhpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($fakePhpFile)
        ]
    ]
]);
@unlink($fakePhpFile);
assertTrue(
    $resUploadPhp['http_code'] === 400
    && strpos((string) ($resUploadPhp['body']['message'] ?? ''), 'Formato de imagem inválido') !== false,
    "Upload de script PHP disfarçado com extensão .jpg é bloqueado pela inspeção de MIME real (finfo)"
);

// Arquivo com magic bytes PNG (\x89PNG\r\n\x1a\n) mas conteúdo inválido/corrompido
$corruptPngFile = tempnam(sys_get_temp_dir(), 'corrupt_png_') . '.png';
file_put_contents($corruptPngFile, "\x89PNG\r\n\x1a\nCORRUPT_PAYLOAD_NOT_AN_IMAGE");
$resUploadCorrupt = invokeEndpoint('api_admin_produtos_v2.php', [
    'method' => 'POST',
    'session' => $sessAdmin,
    'headers' => ['X-CSRF-Token' => 'csrf_admin_fat'],
    'post' => [
        'nome' => 'Produto Imagem Corrompida',
        'categoria' => 'Frutas',
        'preco' => '10.00',
        'unidade' => 'un',
        'estoque' => '5'
    ],
    'files' => [
        'imagem' => [
            'name' => 'corrupt.png',
            'type' => 'image/png',
            'tmp_name' => $corruptPngFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($corruptPngFile)
        ]
    ]
]);
@unlink($corruptPngFile);
assertEquals(400, $resUploadCorrupt['http_code'], "Upload de arquivo com magic bytes falsos/corrompidos é bloqueado na decodificação GD");

// Arquivo excedendo limite de 5MB
$dummyFile = tempnam(sys_get_temp_dir(), 'oversize_');
file_put_contents($dummyFile, 'x');
$resUploadGrande = invokeEndpoint('api_admin_produtos_v2.php', [
    'method' => 'POST',
    'session' => $sessAdmin,
    'headers' => ['X-CSRF-Token' => 'csrf_admin_fat'],
    'post' => [
        'nome' => 'Produto Imagem Grande',
        'categoria' => 'Frutas',
        'preco' => '10.00',
        'unidade' => 'un',
        'estoque' => '5'
    ],
    'files' => [
        'imagem' => [
            'name' => 'huge.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $dummyFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024 // 6 MB > 5 MB
        ]
    ]
]);
@unlink($dummyFile);
assertTrue(
    $resUploadGrande['http_code'] === 400
    && strpos((string) ($resUploadGrande['body']['message'] ?? ''), '5MB') !== false,
    "Upload de imagem acima de 5MB é bloqueado com HTTP 400"
);

// 3.6 Rate limit sob concorrência (api_login_v2.php tem limite de 10 req / 60s)
$pdo->exec("DELETE FROM rate_limits");
$pdo->exec("INSERT INTO rate_limits (ip, endpoint, attempts, first_attempt) VALUES ('10.99.99.99', '/api_login_v2.php', 9, " . time() . ")");

$rl1 = invokeEndpoint('api_login_v2.php', [
    'method' => 'POST',
    'remote_addr' => '10.99.99.99',
    'session' => ['csrf_token' => 'csrf_rl'],
    'headers' => ['X-CSRF-Token' => 'csrf_rl'],
    'json' => ['email' => 'invalido@teste.com', 'senha' => 'errada']
]);
$rl2 = invokeEndpoint('api_login_v2.php', [
    'method' => 'POST',
    'remote_addr' => '10.99.99.99',
    'session' => ['csrf_token' => 'csrf_rl'],
    'headers' => ['X-CSRF-Token' => 'csrf_rl'],
    'json' => ['email' => 'invalido@teste.com', 'senha' => 'errada']
]);
assertEquals(200, $rl1['http_code'], "10ª tentativa dentro do limite (10/60s) é processada normalmente (HTTP 200)");
assertEquals(429, $rl2['http_code'], "11ª tentativa excede o rate limit e retorna HTTP 429 (Too Many Requests)");

// 3.7 CORS não autorizado vs autorizado (cors.php)
$resCorsMalicioso = invokeEndpoint('api_catalogo_v2.php', [
    'method' => 'GET',
    'headers' => ['Origin' => 'https://atacante.malicioso.com', 'Host' => 'clubefazbem.com'],
    'env' => ['AMBIENTE' => 'producao']
]);
$allowOriginMalicioso = false;
foreach ($resCorsMalicioso['headers'] as $h) {
    if (stripos($h, 'Access-Control-Allow-Origin:') === 0) {
        $allowOriginMalicioso = true;
    }
}
assertTrue($allowOriginMalicioso === false, "CORS não emite Access-Control-Allow-Origin para origem externa não autorizada");

// Origem localhost:3000 bloqueada quando AMBIENTE=producao
$resCorsLocalInProd = invokeEndpoint('api_catalogo_v2.php', [
    'method' => 'GET',
    'headers' => ['Origin' => 'http://localhost:3000', 'Host' => 'clubefazbem.com'],
    'env' => ['AMBIENTE' => 'producao']
]);
$allowOriginLocalInProd = false;
foreach ($resCorsLocalInProd['headers'] as $h) {
    if (stripos($h, 'Access-Control-Allow-Origin:') === 0) {
        $allowOriginLocalInProd = true;
    }
}
assertTrue($allowOriginLocalInProd === false, "CORS bloqueia origem de desenvolvimento (localhost:3000) quando AMBIENTE=producao");

// Origem autorizada recebe cabeçalho Access-Control-Allow-Origin
$resCorsAutorizado = invokeEndpoint('api_catalogo_v2.php', [
    'method' => 'GET',
    'headers' => ['Origin' => 'https://clubefazbem.com', 'Host' => 'clubefazbem.com'],
    'env' => ['AMBIENTE' => 'producao']
]);
$allowOriginHeader = '';
foreach ($resCorsAutorizado['headers'] as $h) {
    if (stripos($h, 'Access-Control-Allow-Origin:') === 0) {
        $allowOriginHeader = trim(substr($h, strlen('Access-Control-Allow-Origin:')));
    }
}
assertEquals('https://clubefazbem.com', $allowOriginHeader, "CORS emite Access-Control-Allow-Origin exato para domínio oficial autorizado");

// 3.8 Invalidação de sessão ativa em endpoint real após recuperação de senha
$pdo->exec("DELETE FROM rate_limits");
$tokensReset = [];
Usuario::setCanalNotificacao(function (string $email, string $rawToken) use (&$tokensReset) {
    $tokensReset[] = $rawToken;
});
$usuarioModel = new Usuario();
$usuarioModel->solicitarRecuperacaoSenha('carlos@email.com', '127.0.0.1');
$tokenCapturado = end($tokensReset);

$versaoAntesReset = (int) $pdo->query("SELECT session_version FROM usuarios WHERE id = 4")->fetchColumn();
$sessaoAtivaAntiga = [
    'usuario_id' => 4,
    'tipo_usuario' => 'cliente',
    'csrf_token' => 'csrf_sess_antiga',
    'session_version' => $versaoAntesReset
];

// Confirma que a sessão funcionava antes do reset
$checkAntes = invokeEndpoint('api_meus_pedidos_v2.php', ['session' => $sessaoAtivaAntiga]);
assertEquals(200, $checkAntes['http_code'], "Sessão autenticada funciona normalmente antes da redefinição de senha (200)");

// Executa redefinição de senha com o token
$usuarioModel->redefinirSenhaComToken($tokenCapturado, 'NovaSenha#Fase5', 'carlos@email.com', '127.0.0.1');

// A mesma sessão antiga agora deve ser recusada com HTTP 403 em qualquer endpoint protegido!
$checkDepois = invokeEndpoint('api_meus_pedidos_v2.php', ['session' => $sessaoAtivaAntiga]);
assertEquals(403, $checkDepois['http_code'], "Sessão anterior é invalidada automaticamente em endpoints da API após recuperação de senha (403)");

$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "RESULTADO FASE 5: {$assertions} asserções | {$failures} falhas\n");
fwrite(STDOUT, "============================================================\n");

exit($failures > 0 ? 1 : 0);
