<?php
/**
 * Suíte Automatizada de Testes — Fase 2 (Autorização Operacional e Máquina de Estados)
 * Executa em banco MySQL/MariaDB isolado (fazbem_v2_test) sem afetar dados de produção.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Env.php';
require_once __DIR__ . '/../app/Database.php';
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

function assertThrows(callable $fn, int $expectedCode, string $message) {
    try {
        $fn();
        assertTrue(false, $message . " (nenhuma exceção lançada)");
    } catch (Exception $e) {
        assertEquals($expectedCode, (int) $e->getCode(), $message);
    }
}

$socket = getenv('TEST_DB_SOCKET') ?: '';
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') !== false ? getenv('TEST_DB_PASS') : '';
$testDbName = 'fazbem_v2_test';

if ($socket !== '') {
    $adminDsn = "mysql:unix_socket={$socket};charset=utf8mb4";
} else {
    $portPart = $port !== '' ? ";port={$port}" : "";
    $adminDsn = "mysql:host={$host}{$portPart};charset=utf8mb4";
}

$pdo = new PDO($adminDsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
$pdo->exec("CREATE DATABASE `{$testDbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$testDbName}`");

Database::setConexao($pdo);

fwrite(STDOUT, "\n=== 1. Migração 002 (DOWN / UP) e Estrutura de Auditoria ===\n");
$schemaSql = str_replace('`fazbem_v2`', "`{$testDbName}`", file_get_contents(__DIR__ . '/../db_schema.sql'));
$pdo->exec($schemaSql);

$downSql = file_get_contents(__DIR__ . '/../migrations/002_fase2_autorizacao_estados_down.sql');
$pdo->exec($downSql);
$tblAfterDown = $pdo->query("SHOW TABLES LIKE 'historico_status_pedidos'")->fetchAll();
assertEquals(0, count($tblAfterDown), "Migração 002 DOWN removeu tabela historico_status_pedidos");

$upSql = file_get_contents(__DIR__ . '/../migrations/002_fase2_autorizacao_estados_up.sql');
$pdo->exec($upSql);
$tblAfterUp = $pdo->query("SHOW TABLES LIKE 'historico_status_pedidos'")->fetchAll();
assertEquals(1, count($tblAfterUp), "Migração 002 UP recriou tabela historico_status_pedidos");

$idxRotas = $pdo->query("SHOW INDEX FROM rotas WHERE Key_name = 'idx_rotas_entregador_data_status'")->fetchAll();
assertTrue(count($idxRotas) >= 3, "Índice composto idx_rotas_entregador_data_status criado em rotas");

// Massa de dados para testes operacionais
$pdo->exec("INSERT INTO usuarios (id, nome, email, senha, telefone, endereco, tipo_usuario) VALUES
    (1, 'Admin', 'admin@teste.com', 'h', '55900000001', 'Sede', 'admin'),
    (2, 'Entregador A', 'entA@teste.com', 'h', '55900000002', 'Base', 'entregador'),
    (3, 'Entregador B', 'entB@teste.com', 'h', '55900000003', 'Base', 'entregador'),
    (4, 'Separador', 'sep@teste.com', 'h', '55900000004', 'Base', 'separador'),
    (10, 'Cliente', 'cli@teste.com', 'h', '55900000010', 'Rua X, 100', 'cliente')
    ON DUPLICATE KEY UPDATE nome = VALUES(nome), tipo_usuario = VALUES(tipo_usuario)");

$pdo->exec("INSERT INTO produtos (id, nome, categoria, tipo_venda, preco, unidade, estoque_atual, peso_estimado_g) VALUES
    (1, 'Cenoura', 'Legumes', 'Fracionado', 10.00, 'kg', 50.000, 1000),
    (2, 'Alface', 'Verduras', 'Inteiro', 4.00, 'un', 30.000, 200)");

$pedidoModel = new Pedido();

fwrite(STDOUT, "\n=== 2. Escopo do Separador, Validações de Pesagem e Transição 'Em separação' -> 'Aguardando Entrega' ===\n");
// Pedido 201: válido na fila desta semana
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (201, 10, 14.00, 'Pendente', 'Em separação', 'Extra', NOW())");
$pdo->exec("INSERT INTO itens_pedido (id, pedido_id, produto_id, quantidade, preco_unitario) VALUES
            (2001, 201, 1, 1.000, 10.00),
            (2002, 201, 2, 1.000, 4.00)");

// Pedido 202: pedido antigo fora do ciclo operacional (2 semanas atrás)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (202, 10, 10.00, 'Pendente', 'Em separação', 'Extra', DATE_SUB(NOW(), INTERVAL 15 DAY))");
$pdo->exec("INSERT INTO itens_pedido (id, pedido_id, produto_id, quantidade, preco_unitario) VALUES
            (2003, 202, 1, 1.000, 10.00)");

// Pedido 203: pedido cancelado
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, data_pedido)
            VALUES (203, 10, 10.00, 'Cancelado', 'Em separação', 'Extra', NOW())");
$pdo->exec("INSERT INTO itens_pedido (id, pedido_id, produto_id, quantidade, preco_unitario) VALUES
            (2004, 203, 1, 1.000, 10.00)");

// 2.1 Separador tentando separar pedido fora do ciclo operacional -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(202, [['item_id' => 2003, 'quantidade_real' => 1.0]], 4, 'separador');
}, 403, "Separador bloqueado ao tentar separar pedido fora do ciclo operacional (403)");

// 2.2 Separador tentando separar pedido cancelado -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(203, [['item_id' => 2004, 'quantidade_real' => 1.0]], 4, 'separador');
}, 403, "Separador bloqueado ao tentar separar pedido cancelado (403)");

// 2.3 Entregador tentando realizar separação -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(201, [
        ['item_id' => 2001, 'quantidade_real' => 1.1],
        ['item_id' => 2002, 'quantidade_real' => 1.0]
    ], 2, 'entregador');
}, 403, "Entregador bloqueado ao tentar registrar separação/pesagem (403)");

// 2.4 Pesagem parcial (faltando item 2002) -> 400
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(201, [['item_id' => 2001, 'quantidade_real' => 1.1]], 4, 'separador');
}, 400, "Pesagem parcial incompleta bloqueada (400)");

// 2.5 Pesagem com peso negativo -> 400
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(201, [
        ['item_id' => 2001, 'quantidade_real' => -0.5],
        ['item_id' => 2002, 'quantidade_real' => 1.0]
    ], 4, 'separador');
}, 400, "Pesagem com quantidade negativa bloqueada (400)");

// 2.6 Pesagem válida pelo separador -> sucesso + auditoria
$okSep = $pedidoModel->registrarPesagemSeparacao(201, [
    ['item_id' => 2001, 'quantidade_real' => 1.250], // R$ 12,50
    ['item_id' => 2002, 'quantidade_real' => 1.000]  // R$ 4,00 -> Total R$ 16,50
], 4, 'separador', 'Pesagem concluída na bancada 1');
assertTrue($okSep, "Separação válida concluída com sucesso");

$p201 = $pdo->query("SELECT valor_total, status_entrega FROM pedidos WHERE id = 201")->fetch();
assertEquals(16.50, floatval($p201['valor_total']), "Valor total recalculado após pesagem (16.50)");
assertEquals('Aguardando Entrega', $p201['status_entrega'], "Status atualizado para 'Aguardando Entrega'");

// 2.7 Bloqueio de repesagem de pedido já separado
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->registrarPesagemSeparacao(201, [
        ['item_id' => 2001, 'quantidade_real' => 1.0],
        ['item_id' => 2002, 'quantidade_real' => 1.0]
    ], 4, 'separador');
}, 400, "Repesagem de pedido que já saiu de 'Em separação' bloqueada (400)");

fwrite(STDOUT, "\n=== 3. Restrição de Entregador por Rota, Data e Atribuição ===\n");
// Criar rotas:
// Rota 10: Entregador A (id=2), data=CURDATE(), status='Pendente'
// Rota 11: Entregador B (id=3), data=CURDATE(), status='Pendente'
// Rota 12: Entregador A (id=2), data=ONTEM, status='Pendente'
$pdo->exec("INSERT INTO rotas (id, entregador_id, data_rota, status) VALUES
    (10, 2, CURDATE(), 'Pendente'),
    (11, 3, CURDATE(), 'Pendente'),
    (12, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Pendente')");

// Pedido 204: sem rota atribuída ('Aguardando Entrega')
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, rota_id, data_entrega)
            VALUES (204, 10, 25.00, 'Pago', 'Aguardando Entrega', NULL, CURDATE())");
// Pedido 205: na Rota 11 (do Entregador B)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, rota_id, data_entrega)
            VALUES (205, 10, 25.00, 'Pago', 'Aguardando Entrega', 11, CURDATE())");
// Pedido 206: na Rota 12 (do Entregador A, porém data de ontem)
$pdo->exec("INSERT INTO pedidos (id, usuario_id, valor_total, status_pagamento, status_entrega, rota_id, data_entrega)
            VALUES (206, 10, 25.00, 'Pago', 'Aguardando Entrega', 12, DATE_SUB(CURDATE(), INTERVAL 1 DAY))");

// Vincular Pedido 201 à Rota 10 (Entregador A, hoje)
$pdo->exec("UPDATE pedidos SET rota_id = 10, data_entrega = CURDATE() WHERE id = 201");

// 3.1 Entregador A tentando iniciar entrega de pedido sem rota -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(204, 'entrega', 'Saiu para entrega', 2, 'entregador');
}, 403, "Entregador bloqueado ao tentar alterar pedido sem rota atribuída (403)");

// 3.2 Entregador A tentando iniciar entrega de pedido da rota do Entregador B -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(205, 'entrega', 'Saiu para entrega', 2, 'entregador');
}, 403, "Entregador A bloqueado ao tentar alterar pedido da rota do Entregador B (403)");

// 3.3 Entregador A tentando iniciar entrega de rota de outra data -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(206, 'entrega', 'Saiu para entrega', 2, 'entregador');
}, 403, "Entregador A bloqueado ao tentar alterar pedido de rota de outra data (403)");

// 3.4 Separador tentando iniciar rota de entrega -> 403
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'entrega', 'Saiu para entrega', 4, 'separador');
}, 403, "Separador bloqueado ao tentar alterar status para 'Saiu para entrega' (403)");

// 3.5 Entregador A tentando pular etapa ('Aguardando Entrega' -> 'Entregue') -> 400
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'entrega', 'Entregue', 2, 'entregador');
}, 400, "Bloqueio de salto direto 'Aguardando Entrega' -> 'Entregue' sem sair para entrega (400)");

// 3.6 Entregador A executa transição válida: 'Aguardando Entrega' -> 'Saiu para entrega'
$pedidoModel->atualizarStatus(201, 'entrega', 'Saiu para entrega', 2, 'entregador', 'Início da rota pelo entregador');
$rota10Status = $pdo->query("SELECT status FROM rotas WHERE id = 10")->fetchColumn();
assertEquals('Rota Iniciada', $rota10Status, "Status da Rota 10 atualizado automaticamente para 'Rota Iniciada'");

// 3.7 Entregador A executa transição válida: 'Saiu para entrega' -> 'Entregue'
$pedidoModel->atualizarStatus(201, 'entrega', 'Entregue', 2, 'entregador', 'Entregue em mãos ao cliente');
$p201Final = $pdo->query("SELECT status_entrega, entregue_em FROM pedidos WHERE id = 201")->fetch();
assertEquals('Entregue', $p201Final['status_entrega'], "Pedido 201 marcado como 'Entregue'");
assertTrue(!empty($p201Final['entregue_em']), "Timestamp entregue_em preenchido ao finalizar entrega");

$rota10Final = $pdo->query("SELECT status FROM rotas WHERE id = 10")->fetchColumn();
assertEquals('Concluída', $rota10Final, "Rota 10 marcada automaticamente como 'Concluída' após última entrega");

fwrite(STDOUT, "\n=== 4. Bloqueio de Retorno de Estados Finalizados e Transições de Pagamento ===\n");
// 4.1 Tentativa de reverter 'Entregue' -> 'Saiu para entrega' (mesmo por admin) -> 400
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'entrega', 'Saiu para entrega', 1, 'admin', 'Tentativa de reversão');
}, 400, "Bloqueio de retorno de pedido já 'Entregue' para 'Saiu para entrega' (400)");

// 4.2 Tentativa de reverter 'Entregue' -> 'Em separação' -> 400
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'entrega', 'Em separação', 1, 'admin');
}, 400, "Bloqueio de retorno de pedido já 'Entregue' para 'Em separação' (400)");

// 4.3 Transição de pagamento válida por admin: 'Pendente' -> 'Pago'
$pedidoModel->atualizarStatus(201, 'pagamento', 'Pago', 1, 'admin', 'Confirmação financeira manual');
$p201Pag = $pdo->query("SELECT status_pagamento FROM pedidos WHERE id = 201")->fetchColumn();
assertEquals('Pago', $p201Pag, "Pagamento do Pedido 201 atualizado para 'Pago'");

// 4.4 Bloqueio de reversão 'Pago' -> 'Pendente'
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'pagamento', 'Pendente', 1, 'admin');
}, 400, "Bloqueio de retorno de pagamento 'Pago' para 'Pendente' (400)");

// 4.5 Bloqueio de cancelamento operacional de pedido já entregue
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(201, 'pagamento', 'Cancelado', 1, 'admin');
}, 400, "Bloqueio de cancelamento manual de pedido já entregue (400)");

// 4.6 Bloqueio de retorno de pagamento 'Cancelado' -> 'Pago' (Pedido 203)
assertThrows(function () use ($pedidoModel) {
    $pedidoModel->atualizarStatus(203, 'pagamento', 'Pago', 1, 'admin');
}, 400, "Bloqueio de retorno de pagamento 'Cancelado' para 'Pago' (400)");

fwrite(STDOUT, "\n=== 5. Verificação da Trilha de Auditoria (historico_status_pedidos) ===\n");
$historico = $pdo->query("SELECT usuario_id, papel_usuario, campo, estado_anterior, estado_novo, motivo, criado_em FROM historico_status_pedidos WHERE pedido_id = 201 ORDER BY id ASC")->fetchAll();
assertEquals(4, count($historico), "Foram registrados exatamente 4 eventos auditados para o Pedido 201");

assertEquals('separador', $historico[0]['papel_usuario'], "Evento 1 registrado pelo papel 'separador'");
assertEquals('Em separação', $historico[0]['estado_anterior'], "Evento 1: estado_anterior = 'Em separação'");
assertEquals('Aguardando Entrega', $historico[0]['estado_novo'], "Evento 1: estado_novo = 'Aguardando Entrega'");

assertEquals('entregador', $historico[1]['papel_usuario'], "Evento 2 registrado pelo papel 'entregador'");
assertEquals('Aguardando Entrega', $historico[1]['estado_anterior'], "Evento 2: estado_anterior = 'Aguardando Entrega'");
assertEquals('Saiu para entrega', $historico[1]['estado_novo'], "Evento 2: estado_novo = 'Saiu para entrega'");

assertEquals('entregador', $historico[2]['papel_usuario'], "Evento 3 registrado pelo papel 'entregador'");
assertEquals('Saiu para entrega', $historico[2]['estado_anterior'], "Evento 3: estado_anterior = 'Saiu para entrega'");
assertEquals('Entregue', $historico[2]['estado_novo'], "Evento 3: estado_novo = 'Entregue'");

assertEquals('admin', $historico[3]['papel_usuario'], "Evento 4 registrado pelo papel 'admin'");
assertEquals('status_pagamento', $historico[3]['campo'], "Evento 4: campo = 'status_pagamento'");
assertEquals('Pendente', $historico[3]['estado_anterior'], "Evento 4: estado_anterior = 'Pendente'");
assertEquals('Pago', $historico[3]['estado_novo'], "Evento 4: estado_novo = 'Pago'");
assertTrue(!empty($historico[3]['motivo']) && !empty($historico[3]['criado_em']), "Auditoria contém motivo e timestamp criado_em");

$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "Total de asserções: {$assertions} | Falhas: {$failures}\n");
fwrite(STDOUT, "============================================================\n");

exit($failures > 0 ? 1 : 0);
