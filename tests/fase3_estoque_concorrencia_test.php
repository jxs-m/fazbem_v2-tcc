<?php
// Caminho: tests/fase3_estoque_concorrencia_test.php
// Suite de testes automatizados da Fase 3 — Estoque, Movimentações e Concorrência

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Env.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Models/Pedido.php';
require_once __DIR__ . '/../app/Models/Produto.php';

$assertions = 0;
$failures = 0;

function assertTrue(bool $condition, string $message): void {
    global $assertions, $failures;
    $assertions++;
    if ($condition) {
        echo "  [PASS] {$message}\n";
    } else {
        $failures++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== INICIANDO TESTES AUTOMATIZADOS: FASE 3 (ESTOQUE E CONCORRÊNCIA) ===\n\n";

$pdo = Database::getConexao();
$schemaSql = str_replace('`fazbem_v2`', '`' . (getenv('DB_NAME') ?: 'fazbem_v2_test') . '`', file_get_contents(__DIR__ . '/../db_schema.sql'));
$seedsSql = str_replace('`fazbem_v2`', '`' . (getenv('DB_NAME') ?: 'fazbem_v2_test') . '`', file_get_contents(__DIR__ . '/../db_seeds.sql'));
$pdo->exec($schemaSql);
$pdo->exec($seedsSql);

$pedidoModel = new Pedido();
$produtoModel = new Produto();

// ------------------------------------------------------------------------------
// 1. Teste de Reversibilidade da Migração 003 (DOWN + UP)
// ------------------------------------------------------------------------------
echo "1. Testando reversibilidade da migração 003 (DOWN -> UP)...\n";
$downSql = file_get_contents(__DIR__ . '/../migrations/003_fase3_estoque_concorrencia_down.sql');
$upSql = file_get_contents(__DIR__ . '/../migrations/003_fase3_estoque_concorrencia_up.sql');

$pdo->exec($downSql);
$colAfterDown = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ativo'")->fetch();
assertTrue($colAfterDown === false, "Migração 003 DOWN removeu coluna 'ativo' de produtos");

$pdo->exec($upSql);
$colAfterUp = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ativo'")->fetch();
$colPedAfterUp = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'semana_ativa_chave'")->fetch();
$colMovAfterUp = $pdo->query("SHOW COLUMNS FROM movimentacoes_estoque LIKE 'pedido_id'")->fetch();
assertTrue($colAfterUp !== false && $colPedAfterUp !== false && $colMovAfterUp !== false, "Migração 003 UP recriou colunas e índices de estoque e concorrência");

// ------------------------------------------------------------------------------
// 2. Validações de Carrinho (Pedido::processarCarrinho)
// ------------------------------------------------------------------------------
echo "\n2. Testando validações de carrinho (ativo, temporário expirado, duplicado, unidade, precisão, limites, estoque)...\n";

// 2.1 Produto inativo (ativo = 0)
$pdo->exec("INSERT INTO produtos (id, nome, categoria, tipo_venda, preco, unidade, estoque_atual, peso_estimado_g, ativo)
            VALUES (901, 'Alface Inativa', 'Verduras', 'Inteiro', 4.00, 'un', 20.000, 250, 0)
            ON DUPLICATE KEY UPDATE ativo = 0, estoque_atual = 20.000");

$rejeitouInativo = false;
try {
    $pedidoModel->processarCarrinho([['id' => 901, 'quantidade' => 1]]);
} catch (Exception $e) {
    $rejeitouInativo = strpos($e->getMessage(), 'inativo') !== false;
}
assertTrue($rejeitouInativo, "Carrinho rejeita produto com ativo = 0");

// 2.2 Produto temporário expirado
$pdo->exec("INSERT INTO produtos (id, nome, categoria, tipo_venda, preco, unidade, estoque_atual, peso_estimado_g, temporario, duracao_dias, criado_em, ativo)
            VALUES (902, 'Morango Sazonal Expirado', 'Frutas', 'Inteiro', 12.00, 'bandeja', 15.000, 300, 1, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), 1)
            ON DUPLICATE KEY UPDATE temporario = 1, duracao_dias = 2, criado_em = DATE_SUB(NOW(), INTERVAL 5 DAY), ativo = 1");

$rejeitouExpirado = false;
try {
    $pedidoModel->processarCarrinho([['id' => 902, 'quantidade' => 1]]);
} catch (Exception $e) {
    $rejeitouExpirado = strpos($e->getMessage(), 'expirado') !== false;
}
assertTrue($rejeitouExpirado, "Carrinho rejeita produto temporário com prazo expirado");

// 2.3 Produto duplicado no mesmo carrinho
$rejeitouDuplicado = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 1, 'quantidade' => 1],
        ['id' => 1, 'quantidade' => 2]
    ]);
} catch (Exception $e) {
    $rejeitouDuplicado = strpos($e->getMessage(), 'duplicado') !== false;
}
assertTrue($rejeitouDuplicado, "Carrinho rejeita produto duplicado no mesmo pedido");

// 2.4 Compra em gramas para produto vendido apenas Inteiro
$rejeitouGramasInteiro = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 1, 'input_qtd' => 200, 'tipo_compra' => 'Gramas']
    ]);
} catch (Exception $e) {
    $rejeitouGramasInteiro = strpos($e->getMessage(), 'inteiro') !== false;
}
assertTrue($rejeitouGramasInteiro, "Carrinho rejeita compra em 'Gramas' para produto 'Inteiro'");

// 2.5 Compra fracionada para 'Unidade' (ex: 1.5 unidades)
$rejeitouUnidadeFracionada = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 1, 'input_qtd' => 1.5, 'tipo_compra' => 'Unidade']
    ]);
} catch (Exception $e) {
    $rejeitouUnidadeFracionada = strpos($e->getMessage(), 'inteiro') !== false;
}
assertTrue($rejeitouUnidadeFracionada, "Carrinho rejeita quantidade fracionada quando tipo_compra = 'Unidade'");

// 2.6 Precisão decimal inválida (> 3 casas decimais)
$rejeitouPrecisao = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 3, 'quantidade' => '1.2345']
    ]);
} catch (Exception $e) {
    $rejeitouPrecisao = strpos($e->getMessage(), 'Precisão decimal') !== false;
}
assertTrue($rejeitouPrecisao, "Carrinho rejeita quantidade com mais de 3 casas decimais");

// 2.7 Limites mínimo e máximo (Unidades: 1..50, Gramas: 50g..20000g)
$rejeitouMinGramas = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 3, 'input_qtd' => 20, 'tipo_compra' => 'Gramas']
    ]);
} catch (Exception $e) {
    $rejeitouMinGramas = strpos($e->getMessage(), 'limites') !== false;
}
assertTrue($rejeitouMinGramas, "Carrinho rejeita compra em gramas abaixo de 50g");

$rejeitouMaxUnidades = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 1, 'input_qtd' => 55, 'tipo_compra' => 'Unidade']
    ]);
} catch (Exception $e) {
    $rejeitouMaxUnidades = strpos($e->getMessage(), 'limites') !== false;
}
assertTrue($rejeitouMaxUnidades, "Carrinho rejeita compra acima de 50 unidades");

// 2.8 Estoque insuficiente no carrinho
$rejeitouSemEstoque = false;
try {
    $pedidoModel->processarCarrinho([
        ['id' => 3, 'input_qtd' => 19000, 'tipo_compra' => 'Gramas'] // 19kg < 60kg ok, vamos testar produto com 0.200kg
    ]);
    $pdo->exec("UPDATE produtos SET estoque_atual = 0.200 WHERE id = 3");
    $pedidoModel->processarCarrinho([
        ['id' => 3, 'input_qtd' => 500, 'tipo_compra' => 'Gramas'] // Pede 0.500kg tendo apenas 0.200kg
    ]);
} catch (Exception $e) {
    $rejeitouSemEstoque = strpos($e->getMessage(), 'Estoque insuficiente') !== false;
} finally {
    $pdo->exec("UPDATE produtos SET estoque_atual = 60.000 WHERE id = 3");
}
assertTrue($rejeitouSemEstoque, "Carrinho rejeita item quando estoque_atual < quantidade solicitada");

// 2.9 Conversão correta de unidade (Cenoura Orgânica id=3, Fracionado em kg: 500g = 0.500 kg)
$resCarrinho = $pedidoModel->processarCarrinho([
    ['id' => 3, 'input_qtd' => 500, 'tipo_compra' => 'Gramas']
]);
$itemCenoura = $resCarrinho['itens_processados'][0];
assertTrue(
    abs($itemCenoura['quantidade'] - 0.500) < 0.0001 && abs($itemCenoura['estoque_decremento'] - 0.500) < 0.0001,
    "Conversão de 500g de produto em 'kg' resulta em 0.500 kg tanto na quantidade quanto no estoque_decremento"
);

// ------------------------------------------------------------------------------
// 3. Reserva Transacional de Estoque, Limite Semanal e Concorrência
// ------------------------------------------------------------------------------
echo "\n3. Testando reserva transacional de estoque e proteção de limite semanal contra concorrência...\n";

// Limpa pedidos da semana do cliente de teste (id=2, Maria)
$pdo->exec("DELETE FROM movimentacoes_estoque");
$pdo->exec("DELETE FROM historico_status_pedidos");
$pdo->exec("DELETE FROM itens_pedido");
$pdo->exec("DELETE FROM pedidos WHERE usuario_id = 2");
$pdo->exec("UPDATE produtos SET estoque_atual = 10.000, ativo = 1 WHERE id = 1"); // Alface Crespa (un)
$pdo->exec("UPDATE produtos SET estoque_atual = 5.000, ativo = 1 WHERE id = 3");  // Cenoura Orgânica (kg)

$carrinhoMaria = $pedidoModel->processarCarrinho([
    ['id' => 1, 'input_qtd' => 2, 'tipo_compra' => 'Unidade'],   // 2 un Alface
    ['id' => 3, 'input_qtd' => 500, 'tipo_compra' => 'Gramas']   // 0.500 kg Cenoura
]);

$pedidoIdMaria = $pedidoModel->criarPedido(
    2,
    $carrinhoMaria['total_calculado'],
    'PIX',
    $carrinhoMaria['itens_processados'],
    null,
    'Pendente',
    'idem_fase3_maria_1',
    null,
    'Extra'
);

$estAlface = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 1")->fetchColumn();
$estCenoura = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 3")->fetchColumn();
$pedRow = $pdo->query("SELECT estoque_reservado, semana_ativa_chave FROM pedidos WHERE id = {$pedidoIdMaria}")->fetch();

assertTrue(abs($estAlface - 8.000) < 0.0001, "Estoque de Alface decrementado de 10.000 para 8.000 na reserva do pedido");
assertTrue(abs($estCenoura - 4.500) < 0.0001, "Estoque de Cenoura decrementado de 5.000 para 4.500 kg na reserva do pedido");
assertTrue((int) $pedRow['estoque_reservado'] === 1 && !empty($pedRow['semana_ativa_chave']), "Pedido marcado com estoque_reservado=1 e semana_ativa_chave preenchida");

$movsReserva = $pdo->query("SELECT COUNT(*) FROM movimentacoes_estoque WHERE pedido_id = {$pedidoIdMaria} AND tipo = 'Reserva'")->fetchColumn();
assertTrue((int) $movsReserva === 2, "Foram registradas 2 movimentações do tipo 'Reserva' vinculadas ao pedido");

// 3.1 Tentativa de segundo pedido na mesma semana pelo mesmo cliente
$bloqueouSegundoPedido = false;
try {
    $pedidoModel->criarPedido(
        2,
        $carrinhoMaria['total_calculado'],
        'PIX',
        $carrinhoMaria['itens_processados'],
        null,
        'Pendente',
        'idem_fase3_maria_2',
        null,
        'Extra'
    );
} catch (Exception $e) {
    $bloqueouSegundoPedido = strpos($e->getMessage(), 'limite') !== false || strpos($e->getMessage(), 'semana') !== false;
}
assertTrue($bloqueouSegundoPedido, "Segundo pedido ativo na mesma semana é bloqueado em criarPedido()");

// 3.2 Garantia no nível do SGBD (UNIQUE KEY uq_pedidos_usuario_semana_ativa) contra race condition
$bloqueouUniqueDb = false;
try {
    $stmtDup = $pdo->prepare(
        "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, semana_ativa_chave)
         VALUES (2, 15.00, 'Pendente', 'Em separação', 'Extra', ?)"
    );
    $stmtDup->execute([$pedRow['semana_ativa_chave']]);
} catch (PDOException $e) {
    $bloqueouUniqueDb = ($e->getCode() === '23000');
}
assertTrue($bloqueouUniqueDb, "Índice UNIQUE uq_pedidos_usuario_semana_ativa impede duplicidade concorrente no banco");

// 3.3 Proteção contra estoque negativo em concorrência no momento de criarPedido
$pdo->exec("DELETE FROM pedidos WHERE usuario_id = 3");
$pdo->exec("UPDATE produtos SET estoque_atual = 1.000 WHERE id = 1");
// Simula que o carrinho foi processado quando havia 2 unidades, mas outra requisição consumiu 1 antes de criarPedido
$itensConcorrentes = [
    ['produto_id' => 1, 'quantidade' => 2.000, 'preco_unitario' => 3.50, 'estoque_decremento' => 2.000]
];
$bloqueouEstoqueConcorrente = false;
try {
    $pedidoModel->criarPedido(3, 7.00, 'PIX', $itensConcorrentes, null, 'Pendente', 'idem_conc_u3', null, 'Extra');
} catch (Exception $e) {
    $bloqueouEstoqueConcorrente = strpos($e->getMessage(), 'Estoque insuficiente') !== false;
}
$estAlfaceAposFalha = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 1")->fetchColumn();
assertTrue($bloqueouEstoqueConcorrente && abs($estAlfaceAposFalha - 1.000) < 0.0001, "UPDATE condicional impede estoque negativo e faz rollback completo do pedido");

// ------------------------------------------------------------------------------
// 4. Ajustes na Separação/Pesagem (Saída Definitiva, Diferença a Menor/Maior e Substituição)
// ------------------------------------------------------------------------------
echo "\n4. Testando reconciliação de estoque na separação/pesagem (falta parcial, peso maior e substituição)...\n";

$pdo->exec("UPDATE produtos SET estoque_atual = 8.000 WHERE id = 1"); // Alface
$itensPedidoMaria = $pdo->query("SELECT id, produto_id, quantidade FROM itens_pedido WHERE pedido_id = {$pedidoIdMaria} ORDER BY id ASC")->fetchAll();
$itemAlfaceId = (int) $itensPedidoMaria[0]['id'];   // reservado: 2.000 un
$itemCenouraId = (int) $itensPedidoMaria[1]['id'];  // reservado: 0.500 kg (estoque atual Cenoura: 4.500 kg)

// 4.1 Tentativa de pesar quantidade muito maior que o estoque disponível deve falhar e fazer rollback
$falhouPesoExcedente = false;
try {
    $pedidoModel->registrarPesagemSeparacao($pedidoIdMaria, [
        ['item_id' => $itemAlfaceId, 'quantidade_real' => 2.000],
        ['item_id' => $itemCenouraId, 'quantidade_real' => 10.000] // Excede os 4.500kg restantes
    ], 1, 'admin');
} catch (Exception $e) {
    $falhouPesoExcedente = strpos($e->getMessage(), 'Estoque insuficiente') !== false;
}
$estCenouraCheck = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 3")->fetchColumn();
assertTrue($falhouPesoExcedente && abs($estCenouraCheck - 4.500) < 0.0001, "Pesagem acima do estoque disponível falha com rollback sem negativar estoque");

// 4.2 Pesagem com diferença a menor na Alface (1.000 de 2.000 -> estorna 1.000) e diferença a maior na Cenoura (0.600 de 0.500 -> baixa +0.100)
$okPesagem = $pedidoModel->registrarPesagemSeparacao($pedidoIdMaria, [
    ['item_id' => $itemAlfaceId, 'quantidade_real' => 1.000],
    ['item_id' => $itemCenouraId, 'quantidade_real' => 0.600]
], 1, 'admin');

$estAlfacePosPesagem = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 1")->fetchColumn();
$estCenouraPosPesagem = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 3")->fetchColumn();

assertTrue($okPesagem, "Pesagem com ajustes de diferença concluída com sucesso");
assertTrue(abs($estAlfacePosPesagem - 9.000) < 0.0001, "Diferença a menor na pesagem (1 un não utilizada) estornada ao estoque de Alface (8 -> 9)");
assertTrue(abs($estCenouraPosPesagem - 4.400) < 0.0001, "Diferença a maior na pesagem (+0.100 kg) baixada do estoque de Cenoura (4.5 -> 4.4)");

$movEstornoParcial = $pdo->query("SELECT quantidade FROM movimentacoes_estoque WHERE pedido_id = {$pedidoIdMaria} AND produto_id = 1 AND tipo = 'Estorno'")->fetchColumn();
$movSaidaCenoura = $pdo->query("SELECT quantidade FROM movimentacoes_estoque WHERE pedido_id = {$pedidoIdMaria} AND produto_id = 3 AND tipo = 'Saída'")->fetchColumn();
assertTrue(abs((float)$movEstornoParcial - 1.000) < 0.0001 && abs((float)$movSaidaCenoura - 0.600) < 0.0001, "Movimentações de 'Estorno' parcial e 'Saída' definitiva registradas corretamente");

// 4.3 Substituição de item em falta total (quantidade_real = 0 + substituir_falta = true)
// Produto 1 (Alface) tem substituto Produto 2 (Rúcula) em substituicoes_produto
$pdo->exec("INSERT IGNORE INTO substituicoes_produto (produto_original_id, produto_substituto_id) VALUES (1, 2)");
$pdo->exec("UPDATE produtos SET estoque_atual = 10.000, ativo = 1 WHERE id = 1"); // Alface
$pdo->exec("UPDATE produtos SET estoque_atual = 10.000, ativo = 1 WHERE id = 2"); // Rúcula

$carrinhoJoao = $pedidoModel->processarCarrinho([
    ['id' => 1, 'input_qtd' => 2, 'tipo_compra' => 'Unidade']
]);
$pedidoIdJoao = $pedidoModel->criarPedido(3, $carrinhoJoao['total_calculado'], 'PIX', $carrinhoJoao['itens_processados'], null, 'Pendente', 'idem_joao_sub', null, 'Extra');
$itemJoaoId = (int) $pdo->query("SELECT id FROM itens_pedido WHERE pedido_id = {$pedidoIdJoao}")->fetchColumn();

// Estoque Alface caiu para 8.000 na reserva. Na separação, Alface está em falta (0) e é substituída por Rúcula (id=2)
$pedidoModel->registrarPesagemSeparacao($pedidoIdJoao, [
    ['item_id' => $itemJoaoId, 'quantidade_real' => 0, 'substituir_falta' => true]
], 1, 'admin');

$estAlfacePosSub = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 1")->fetchColumn();
$estRuculaPosSub = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 2")->fetchColumn();
$itemAtualizado = $pdo->query("SELECT produto_id, quantidade_real FROM itens_pedido WHERE id = {$itemJoaoId}")->fetch();

assertTrue(
    abs($estAlfacePosSub - 10.000) < 0.0001 && abs($estRuculaPosSub - 8.000) < 0.0001 && (int)$itemAtualizado['produto_id'] === 2,
    "Substituição em falta estorna estoque do produto original (Alface 8->10) e baixa produto substituto (Rúcula 10->8)"
);

// ------------------------------------------------------------------------------
// 5. Estorno de Estoque em Cancelamento de Pedido (Idempotente)
// ------------------------------------------------------------------------------
echo "\n5. Testando estorno de estoque no cancelamento de pedido e liberação da chave semanal...\n";

$pedidoModel->atualizarStatus($pedidoIdMaria, 'pagamento', 'Cancelado', 1, 'admin', 'Cancelamento solicitado pelo cliente');

$estAlfacePosCancel = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 1")->fetchColumn();
$estCenouraPosCancel = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 3")->fetchColumn();
$pedMariaCancel = $pdo->query("SELECT status_pagamento, estoque_reservado, semana_ativa_chave FROM pedidos WHERE id = {$pedidoIdMaria}")->fetch();

// Alface tinha 10.000 (após sub de João), Maria tinha pesado 1.000 -> deve subir para 11.000!
// Cenoura tinha 4.400, Maria tinha pesado 0.600 -> deve voltar para 5.000!
assertTrue(
    abs($estAlfacePosCancel - 11.000) < 0.0001 && abs($estCenouraPosCancel - 5.000) < 0.0001,
    "Cancelamento do pedido restaura exatamente a quantidade efetiva (quantidade_real) ao estoque"
);
assertTrue(
    $pedMariaCancel['status_pagamento'] === 'Cancelado' && (int)$pedMariaCancel['estoque_reservado'] === 0 && $pedMariaCancel['semana_ativa_chave'] === null,
    "Cancelamento zera estoque_reservado e limpa semana_ativa_chave permitindo novo pedido na semana"
);

// Idempotência: chamar estornarEstoquePedido novamente não pode duplicar o estorno
$segundoEstorno = $pedidoModel->estornarEstoquePedido($pedidoIdMaria, 'Repetição de teste');
$estCenouraIdem = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 3")->fetchColumn();
assertTrue($segundoEstorno === false && abs($estCenouraIdem - 5.000) < 0.0001, "estornarEstoquePedido() é idempotente e não duplica devolução de estoque");

// Após cancelamento, Maria consegue criar um novo pedido na mesma semana
$novoPedidoMaria = $pedidoModel->criarPedido(
    2,
    $carrinhoMaria['total_calculado'],
    'PIX',
    $carrinhoMaria['itens_processados'],
    null,
    'Pendente',
    'idem_fase3_maria_novo',
    null,
    'Extra'
);
assertTrue($novoPedidoMaria > $pedidoIdMaria, "Cliente consegue realizar novo pedido na semana após cancelamento do anterior");

// ------------------------------------------------------------------------------
// 6. Teste de api_estoque_v2.php (Bloqueio de Saída/Descarte maior que o estoque)
// ------------------------------------------------------------------------------
echo "\n6. Testando endpoint api_estoque_v2.php contra estoque negativo...\n";

function runEstoqueEndpoint(array $payload): array {
    $runner = tempnam(sys_get_temp_dir(), 'est_runner_') . '.php';
    $code = '<?php
    session_start();
    $_SESSION["usuario_id"] = 1;
    $_SESSION["tipo_usuario"] = "admin";
    $_SESSION["csrf_token"] = "token_teste_estoque";
    $_SERVER["REQUEST_METHOD"] = "POST";
    $_SERVER["HTTP_X_CSRF_TOKEN"] = "token_teste_estoque";
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    register_shutdown_function(function() {
        $out = ob_get_contents();
        ob_end_clean();
        echo json_encode(["http_code" => http_response_code() ?: 200, "body" => json_decode($out, true), "raw" => $out]);
    });
    ob_start();
    require ' . var_export(__DIR__ . '/../api_estoque_v2.php', true) . ';
    ';
    file_put_contents($runner, $code);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $proc = proc_open(['php', $runner], $descriptors, $pipes, __DIR__ . '/..');
    fwrite($pipes[0], json_encode($payload));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    @unlink($runner);

    return json_decode($stdout, true) ?: ['http_code' => 500, 'body' => null, 'raw' => $stdout];
}

$pdo->exec("UPDATE produtos SET estoque_atual = 3.000 WHERE id = 2");
$resDescarteExcesso = runEstoqueEndpoint([
    'produto_id' => 2,
    'tipo' => 'Descarte',
    'quantidade' => 5,
    'unidade_escolhida' => 'maço',
    'descricao' => 'Tentativa de descarte acima do estoque'
]);
$estRuculaMantido = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 2")->fetchColumn();
assertTrue(
    $resDescarteExcesso['http_code'] === 400
    && ($resDescarteExcesso['body']['success'] ?? true) === false
    && strpos((string) ($resDescarteExcesso['body']['message'] ?? ''), 'Estoque insuficiente') !== false
    && abs($estRuculaMantido - 3.000) < 0.0001,
    "api_estoque_v2.php bloqueia Descarte/Saída maior que estoque_atual (HTTP 400) e mantém saldo intacto"
);

$resEntradaOk = runEstoqueEndpoint([
    'produto_id' => 2,
    'tipo' => 'Entrada',
    'quantidade' => 2,
    'unidade_escolhida' => 'maço',
    'descricao' => 'Colheita fresca'
]);
$estRuculaSubiu = (float) $pdo->query("SELECT estoque_atual FROM produtos WHERE id = 2")->fetchColumn();
assertTrue(
    $resEntradaOk['http_code'] === 200 && ($resEntradaOk['body']['success'] ?? false) === true && abs($estRuculaSubiu - 5.000) < 0.0001,
    "api_estoque_v2.php registra Entrada válida e atualiza estoque_atual (3.000 -> 5.000)"
);

echo "\n==================================================================\n";
echo "RESULTADO FASE 3: {$assertions} asserções executadas | {$failures} falhas\n";
echo "==================================================================\n";

exit($failures > 0 ? 1 : 0);
