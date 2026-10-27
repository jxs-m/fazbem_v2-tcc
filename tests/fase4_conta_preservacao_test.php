<?php
// Caminho: tests/fase4_conta_preservacao_test.php
// Suite de testes automatizados da Fase 4 — Conta, Recuperação de Senha, Sessão e Preservação Histórica

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../app/Env.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Models/Usuario.php';
require_once __DIR__ . '/../app/Models/Produto.php';
require_once __DIR__ . '/../app/Models/Pedido.php';

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

echo "=== INICIANDO TESTES AUTOMATIZADOS: FASE 4 (CONTA E PRESERVAÇÃO HISTÓRICA) ===\n\n";

$pdo = Database::getConexao();
$dbName = getenv('DB_NAME') ?: 'fazbem_v2_test';
$schemaSql = str_replace('`fazbem_v2`', "`{$dbName}`", file_get_contents(__DIR__ . '/../db_schema.sql'));
$seedsSql = str_replace('`fazbem_v2`', "`{$dbName}`", file_get_contents(__DIR__ . '/../db_seeds.sql'));
$pdo->exec($schemaSql);
$pdo->exec($seedsSql);

$usuarioModel = new Usuario();
$produtoModel = new Produto();
$pedidoModel = new Pedido();

// ------------------------------------------------------------------------------
// 1. Teste de Reversibilidade da Migração 004 (DOWN -> UP)
// ------------------------------------------------------------------------------
echo "1. Testando reversibilidade da migração 004 (DOWN -> UP)...\n";
$downSql = file_get_contents(__DIR__ . '/../migrations/004_fase4_conta_preservacao_down.sql');
$upSql = file_get_contents(__DIR__ . '/../migrations/004_fase4_conta_preservacao_up.sql');

$pdo->exec($downSql);
$tblTokensDown = $pdo->query("SHOW TABLES LIKE 'recuperacao_senha_tokens'")->fetch();
$colSnapDown = $pdo->query("SHOW COLUMNS FROM itens_pedido LIKE 'produto_nome'")->fetch();
$colSessDown = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'session_version'")->fetch();
assertTrue($tblTokensDown === false && $colSnapDown === false && $colSessDown === false, "Migração 004 DOWN removeu tabelas e colunas da Fase 4 com sucesso");

$pdo->exec($upSql);
$tblTokensUp = $pdo->query("SHOW TABLES LIKE 'recuperacao_senha_tokens'")->fetch();
$tblAuditUp = $pdo->query("SHOW TABLES LIKE 'auditoria_seguranca'")->fetch();
$colSnapUp = $pdo->query("SHOW COLUMNS FROM itens_pedido LIKE 'produto_nome'")->fetch();
$colSessUp = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'session_version'")->fetch();
assertTrue($tblTokensUp !== false && $tblAuditUp !== false && $colSnapUp !== false && $colSessUp !== false, "Migração 004 UP recriou tabelas, snapshots e session_version");

// ------------------------------------------------------------------------------
// 2. Recuperação de Senha (8.1): Token CSPRNG, Hash SHA-256, Uso Único, Expiração, Anti-Enumeração, Sessão e Auditoria
// ------------------------------------------------------------------------------
echo "\n2. Testando fluxo de recuperação de senha, anti-enumeração, hash do token e invalidação de sessão...\n";

$tokensCapturados = [];
Usuario::setCanalNotificacao(function (string $email, string $rawToken, array $user) use (&$tokensCapturados) {
    $tokensCapturados[] = [
        'email' => $email,
        'token' => $rawToken,
        'usuario_id' => (int) $user['id']
    ];
});

// 2.1 Anti-enumeração: resposta idêntica para e-mail existente e inexistente
$resInexistente = $usuarioModel->solicitarRecuperacaoSenha('naoexiste_12345@teste.com', '127.0.0.1');
$resExistente1 = $usuarioModel->solicitarRecuperacaoSenha('carlos@email.com', '127.0.0.1');

assertTrue(
    $resInexistente['success'] === true
    && $resExistente1['success'] === true
    && $resInexistente['message'] === $resExistente1['message'],
    "Resposta uniforme entre conta inexistente e conta existente (previne enumeração de contas)"
);
assertTrue(count($tokensCapturados) === 1, "Canal verificado acionado apenas para a conta existente");

$primeiroToken = $tokensCapturados[0]['token'];
assertTrue(strpos(json_encode($resExistente1), $primeiroToken) === false, "Token nunca é retornado na resposta da solicitação");

// 2.2 Verificar que o banco armazena somente o hash SHA-256 do token
$rowToken1 = $pdo->query("SELECT * FROM recuperacao_senha_tokens WHERE usuario_id = 4 ORDER BY id DESC LIMIT 1")->fetch();
assertTrue(
    $rowToken1['token_hash'] === hash('sha256', $primeiroToken) && $rowToken1['token_hash'] !== $primeiroToken,
    "Banco de dados armazena exclusivamente o hash SHA-256 do token de recuperação"
);

// 2.3 Nova solicitação invalida o token anterior
$usuarioModel->solicitarRecuperacaoSenha('carlos@email.com', '127.0.0.1');
$segundoToken = $tokensCapturados[1]['token'];

$rowToken1After = $pdo->query("SELECT invalidado_em FROM recuperacao_senha_tokens WHERE id = " . (int) $rowToken1['id'])->fetch();
assertTrue($rowToken1After['invalidado_em'] !== null, "Nova solicitação invalida automaticamente o token anterior pendente");

$rejeitouTokenAnterior = false;
try {
    $usuarioModel->redefinirSenhaComToken($primeiroToken, 'NovaSenhaSegura123', 'carlos@email.com', '127.0.0.1');
} catch (Exception $e) {
    $rejeitouTokenAnterior = strpos($e->getMessage(), 'inválido ou expirado') !== false;
}
assertTrue($rejeitouTokenAnterior, "Token anterior invalidado não pode mais ser utilizado");

// 2.4 Teste de expiração curta
$pdo->exec("UPDATE recuperacao_senha_tokens SET expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token_hash = " . $pdo->quote(hash('sha256', $segundoToken)));
$rejeitouExpirado = false;
try {
    $usuarioModel->redefinirSenhaComToken($segundoToken, 'NovaSenhaSegura123', 'carlos@email.com', '127.0.0.1');
} catch (Exception $e) {
    $rejeitouExpirado = strpos($e->getMessage(), 'inválido ou expirado') !== false;
}
assertTrue($rejeitouExpirado, "Token expirado é rejeitado na tentativa de redefinição");

// 2.5 Solicitar 3º token válido, simular sessão ativa antes da troca e redefinir senha
$usuarioModel->solicitarRecuperacaoSenha('carlos@email.com', '127.0.0.1');
$terceiroToken = $tokensCapturados[2]['token'];

// Simula sessão previamente autenticada em outro dispositivo (session_version = 1)
$versaoAntes = (int) $pdo->query("SELECT session_version FROM usuarios WHERE id = 4")->fetchColumn();
$_SESSION['usuario_id'] = 4;
$_SESSION['tipo_usuario'] = 'cliente';
$_SESSION['session_version'] = $versaoAntes;
assertTrue(Security::validateActiveSession() === true && !empty($_SESSION['usuario_id']), "Sessão ativa com session_version atual é válida antes da troca de senha");

// Redefine a senha com o 3º token válido (em outra sessão/contexto sem derrubar variável local até validateActiveSession)
unset($_SESSION['usuario_id']);
$okReset = $usuarioModel->redefinirSenhaComToken($terceiroToken, 'SenhaForte#2026', 'carlos@email.com', '127.0.0.1');
assertTrue($okReset === true, "Redefinição de senha com token válido do canal verificado concluída com sucesso");

// Verifica que a senha realmente mudou e que a versão de sessão foi incrementada
$userCarlos = $usuarioModel->buscarPorEmail('carlos@email.com');
assertTrue(
    password_verify('SenhaForte#2026', $userCarlos['senha']) && (int) $userCarlos['session_version'] === ($versaoAntes + 1),
    "Nova senha gravada com hash seguro e session_version incrementada"
);

// Restaura o estado da sessão antiga (session_version = $versaoAntes) e verifica invalidação automática
$_SESSION['usuario_id'] = 4;
$_SESSION['tipo_usuario'] = 'cliente';
$_SESSION['session_version'] = $versaoAntes;
$sessaoContinuaValida = Security::validateActiveSession();
assertTrue(
    $sessaoContinuaValida === false && empty($_SESSION['usuario_id']),
    "Sessão anterior é imediatamente invalidada após a redefinição de senha"
);

// 2.6 Uso único: tentar reutilizar o 3º token já consumido deve falhar
$rejeitouReuso = false;
try {
    $usuarioModel->redefinirSenhaComToken($terceiroToken, 'OutraSenha#2026', 'carlos@email.com', '127.0.0.1');
} catch (Exception $e) {
    $rejeitouReuso = strpos($e->getMessage(), 'inválido ou expirado') !== false;
}
assertTrue($rejeitouReuso, "Token já utilizado é bloqueado em tentativa de reutilização (uso único)");

// 2.7 Tentativa de bypass no endpoint api_recuperar_senha.php sem token (apenas email + telefone + cpf)
function callRecuperarSenhaEndpoint(array $payload): array {
    $runner = tempnam(sys_get_temp_dir(), 'rec_runner_') . '.php';
    $code = '<?php
    session_start();
    $_SESSION["csrf_token"] = "csrf_rec_test";
    $_SERVER["REQUEST_METHOD"] = "POST";
    $_SERVER["HTTP_X_CSRF_TOKEN"] = "csrf_rec_test";
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    $_SERVER["SCRIPT_NAME"] = "/api_recuperar_senha_test.php";
    register_shutdown_function(function() {
        $out = ob_get_contents();
        ob_end_clean();
        echo json_encode(["http_code" => http_response_code() ?: 200, "body" => json_decode($out, true), "raw" => $out]);
    });
    ob_start();
    require ' . var_export(__DIR__ . '/../api_recuperar_senha.php', true) . ';
    ';
    file_put_contents($runner, $code);

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
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

$pdo->exec("DELETE FROM rate_limits");
$resSemToken = callRecuperarSenhaEndpoint([
    'email' => 'carlos@email.com',
    'telefone' => '55988881111',
    'cpf' => '52998224725',
    'nova_senha' => 'TentativaBypass123'
]);
$userAfterBypass = $usuarioModel->buscarPorEmail('carlos@email.com');
assertTrue(
    $resSemToken['http_code'] === 400
    && ($resSemToken['body']['success'] ?? true) === false
    && password_verify('SenhaForte#2026', $userAfterBypass['senha']),
    "Pessoa sem posse do token do canal verificado não consegue redefinir a senha mesmo informando e-mail, telefone e CPF"
);

// 2.8 Verificar trilha de auditoria sem exposição do token ou senha
$logsAuditoria = $pdo->query("SELECT evento, detalhes FROM auditoria_seguranca")->fetchAll();
$vazouSegredo = false;
foreach ($logsAuditoria as $log) {
    $det = (string) ($log['detalhes'] ?? '');
    if (
        strpos($det, $primeiroToken) !== false
        || strpos($det, $segundoToken) !== false
        || strpos($det, $terceiroToken) !== false
        || strpos($det, 'SenhaForte#2026') !== false
    ) {
        $vazouSegredo = true;
    }
}
assertTrue(count($logsAuditoria) >= 5 && !$vazouSegredo, "Operações de recuperação auditadas em auditoria_seguranca sem registrar tokens em texto puro ou senhas");

// ------------------------------------------------------------------------------
// 3. Preservação Histórica de Dados (8.2): Exclusão Lógica, Snapshots e ON DELETE RESTRICT
// ------------------------------------------------------------------------------
echo "\n3. Testando preservação histórica (snapshots de venda, exclusão lógica de produtos e ON DELETE RESTRICT)...\n";

$pdo->exec("DELETE FROM movimentacoes_estoque");
$pdo->exec("DELETE FROM historico_status_pedidos");
$pdo->exec("DELETE FROM itens_pedido");
$pdo->exec("DELETE FROM pedidos");
$pdo->exec("UPDATE produtos SET nome = 'Alface Crespa Original', unidade = 'un', preco = 3.50, estoque_atual = 30.000, ativo = 1 WHERE id = 1");

// 3.1 Cria pedido que registra snapshot de nome, unidade e preço no momento da venda
$carrinhoSnap = $pedidoModel->processarCarrinho([
    ['id' => 1, 'input_qtd' => 2, 'tipo_compra' => 'Unidade']
]);
$pedidoSnapId = $pedidoModel->criarPedido(
    4,
    $carrinhoSnap['total_calculado'],
    'PIX',
    $carrinhoSnap['itens_processados'],
    null,
    'Pago',
    'idem_snap_fase4',
    'approved',
    'Extra'
);

// Admin altera posteriormente o nome, a unidade e o preço do produto no catálogo
$produtoModel->atualizar(1, 'Alface Gourmet Renomeada', 'Verduras', 19.90, 'bandeja', 28.000, null, 'Inteiro', 0, null, 1);

// Verifica se os itens do pedido preservam o snapshot original da venda
$itensHistoricos = $pedidoModel->buscarItens($pedidoSnapId);
$itemHist = $itensHistoricos[0];
assertTrue(
    $itemHist['nome'] === 'Alface Crespa Original'
    && $itemHist['unidade'] === 'un'
    && abs(floatval($itemHist['preco_unitario']) - 3.50) < 0.001,
    "Snapshot do item vendido preserva nome ('Alface Crespa Original'), unidade ('un') e preço (R$ 3,50) mesmo após edição do produto"
);

// 3.2 Exclusão lógica de produto (Produto::deletar) preserva histórico de vendas
$desativou = $produtoModel->deletar(1);
$prodRow = $pdo->query("SELECT id, ativo FROM produtos WHERE id = 1")->fetch();
$catalogoAtivo = $produtoModel->listarTodos(true);
$idsCatalogoAtivo = array_column($catalogoAtivo, 'id');
$itensAposExclusaoLogica = $pedidoModel->buscarItens($pedidoSnapId);

assertTrue(
    $desativou === true
    && $prodRow !== false
    && (int) $prodRow['ativo'] === 0
    && !in_array(1, array_map('intval', $idsCatalogoAtivo), true)
    && count($itensAposExclusaoLogica) === 1,
    "Produto::deletar() realiza exclusão lógica (ativo = 0), oculta do catálogo ativo e preserva 100% do histórico de vendas"
);

// 3.3 Tentativa de DELETE físico direto em produto com histórico de vendas é bloqueada por ON DELETE RESTRICT
$bloqueouDeleteProduto = false;
try {
    $pdo->exec("DELETE FROM produtos WHERE id = 1");
} catch (PDOException $e) {
    $bloqueouDeleteProduto = ($e->getCode() === '23000');
}
assertTrue($bloqueouDeleteProduto, "Restrição ON DELETE RESTRICT impede exclusão física de produto vinculado a itens vendidos/movimentações");

// 3.4 Tentativa de DELETE físico direto em pedido com itens vendidos é bloqueada por ON DELETE RESTRICT
$bloqueouDeletePedido = false;
try {
    $pdo->exec("DELETE FROM pedidos WHERE id = {$pedidoSnapId}");
} catch (PDOException $e) {
    $bloqueouDeletePedido = ($e->getCode() === '23000');
}
assertTrue($bloqueouDeletePedido, "Restrição ON DELETE RESTRICT impede exclusão física destrutiva de pedido com itens");

// 3.5 Tentativa de DELETE físico de usuário com pedidos/faturas/transações financeiras é bloqueada por ON DELETE RESTRICT
$bloqueouDeleteUsuario = false;
try {
    $pdo->exec("DELETE FROM usuarios WHERE id = 4");
} catch (PDOException $e) {
    $bloqueouDeleteUsuario = ($e->getCode() === '23000');
}
assertTrue($bloqueouDeleteUsuario, "Restrição ON DELETE RESTRICT impede exclusão física de usuário com histórico financeiro/pedidos");

echo "\n==================================================================\n";
echo "RESULTADO FASE 4: {$assertions} asserções executadas | {$failures} falhas\n";
echo "==================================================================\n";

exit($failures > 0 ? 1 : 0);
