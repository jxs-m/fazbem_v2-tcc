<?php
/**
 * Análise Estática, Lint PHP e Validação Estrutural — Fase 5 (CI)
 * Verifica sintaxe PHP, ausência de segredos hardcoded, proteção CSRF em endpoints mutáveis,
 * consultas SQL parametrizadas e integridade das migrações e restrições de preservação histórica.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = realpath(__DIR__ . '/..');
$checks = 0;
$errors = 0;

function checkRule(bool $ok, string $message): void {
    global $checks, $errors;
    $checks++;
    if ($ok) {
        fwrite(STDOUT, "  [OK]   {$message}\n");
    } else {
        $errors++;
        fwrite(STDERR, "  [FAIL] {$message}\n");
    }
}

fwrite(STDOUT, "=== 1. Lint de Sintaxe PHP em todos os arquivos do projeto ===\n");
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    if ($file->getExtension() === 'php') {
        $phpFiles[] = $path;
    }
}
sort($phpFiles);

$lintFailures = [];
foreach ($phpFiles as $phpFile) {
    $rel = substr($phpFile, strlen($rootDir) + 1);
    $cmd = 'php -l ' . escapeshellarg($phpFile) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        $lintFailures[] = "{$rel}: " . implode(' ', $out);
    }
}
checkRule(
    empty($lintFailures),
    "Lint PHP aprovado em " . count($phpFiles) . " arquivos" . (empty($lintFailures) ? "" : " (" . implode("; ", $lintFailures) . ")")
);

fwrite(STDOUT, "\n=== 2. Análise Estática de Segurança (Conflitos, Segredos, SQL Injection, CSRF) ===\n");
$conflictFiles = [];
$hardcodedProdTokens = [];
$directSqlSuperglobals = [];

foreach ($phpFiles as $phpFile) {
    $rel = substr($phpFile, strlen($rootDir) + 1);
    if (strpos($rel, 'tests/') === 0) {
        continue;
    }
    $content = file_get_contents($phpFile);

    if (preg_match('/^(<<<<<<<|=======|>>>>>>>)/m', $content)) {
        $conflictFiles[] = $rel;
    }

    // Credenciais de produção Mercado Pago (APP_USR-...) nunca devem estar hardcoded no código
    if (preg_match('/APP_USR-[0-9a-zA-Z\-]{15,}/', $content)) {
        $hardcodedProdTokens[] = $rel;
    }

    // Proíbe interpolação direta de $_GET, $_POST, $_REQUEST ou $_COOKIE dentro de query()/prepare()/exec()
    if (preg_match('/->\s*(query|prepare|exec)\s*\(\s*["\'][^"\']*\$_(GET|POST|REQUEST|COOKIE)\b/i', $content)) {
        $directSqlSuperglobals[] = $rel;
    }
}

checkRule(empty($conflictFiles), "Nenhum marcador de conflito de merge encontrado no código-fonte");
checkRule(empty($hardcodedProdTokens), "Nenhum token de produção (APP_USR-) hardcoded encontrado nos arquivos PHP");
checkRule(empty($directSqlSuperglobals), "Nenhuma interpolação direta de \$_GET/\$_POST/\$_REQUEST em chamadas PDO query/prepare/exec");

// Verificar proteção CSRF em todos os endpoints api_*.php e logout.php que aceitam mutação (POST/DELETE/PUT)
$mutableEndpointsWithoutCsrf = [];
$apiFiles = glob($rootDir . '/api_*.php');
$apiFiles[] = $rootDir . '/logout.php';

// Endpoints isentos de CSRF de sessão: webhook externo (usa HMAC x-signature) e emissores/leitores públicos GET
$csrfExempt = ['api_mp_webhook.php', 'api_csrf.php', 'api_sessao_v2.php', 'api_mp_key.php', 'api_config.php', 'api_catalogo_v2.php', 'api_meus_pedidos_v2.php', 'api_minha_assinatura_v2.php', 'api_admin_dashboard_v2.php', 'api_admin_relatorio_compras.php'];

foreach ($apiFiles as $apiPath) {
    $base = basename($apiPath);
    if (in_array($base, $csrfExempt, true)) {
        continue;
    }
    $src = file_get_contents($apiPath);
    if (strpos($src, 'Security::checkCSRF()') === false) {
        $mutableEndpointsWithoutCsrf[] = $base;
    }
}
checkRule(
    empty($mutableEndpointsWithoutCsrf),
    "Todos os endpoints mutáveis exigem Security::checkCSRF()" . (empty($mutableEndpointsWithoutCsrf) ? "" : " (faltando em: " . implode(', ', $mutableEndpointsWithoutCsrf) . ")")
);

fwrite(STDOUT, "\n=== 3. Verificação Estática de Schema e Migrações Reversíveis ===\n");
$schemaContent = file_get_contents($rootDir . '/db_schema.sql');

// Garantir que itens_pedido não possui ON DELETE CASCADE destrutivo
$itensPedidoBlock = '';
if (preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`itens_pedido`.*?\)\s*ENGINE=/is', $schemaContent, $m)) {
    $itensPedidoBlock = $m[0];
}
checkRule(
    $itensPedidoBlock !== '' && stripos($itensPedidoBlock, 'ON DELETE CASCADE') === false && stripos($itensPedidoBlock, 'ON DELETE RESTRICT') !== false,
    "Tabela itens_pedido em db_schema.sql usa ON DELETE RESTRICT sem cascata destrutiva"
);

// Verificar existência dos pares UP e DOWN das migrações 001 a 004
$expectedMigrations = [
    '001_fase1_integridade_financeira',
    '002_fase2_autorizacao_estados',
    '003_fase3_estoque_concorrencia',
    '004_fase4_conta_preservacao',
];
$missingMigrations = [];
foreach ($expectedMigrations as $mig) {
    $upFile = $rootDir . "/migrations/{$mig}_up.sql";
    $downFile = $rootDir . "/migrations/{$mig}_down.sql";
    if (!is_file($upFile) || filesize($upFile) === 0) {
        $missingMigrations[] = "{$mig}_up.sql";
    }
    if (!is_file($downFile) || filesize($downFile) === 0) {
        $missingMigrations[] = "{$mig}_down.sql";
    }
}
checkRule(empty($missingMigrations), "Todos os pares de migração reversível (001 a 004 UP/DOWN) estão presentes e não vazios");

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "ANÁLISE ESTÁTICA CONCLUÍDA: {$checks} verificações | {$errors} falhas\n");
fwrite(STDOUT, "============================================================\n");

exit($errors > 0 ? 1 : 0);
