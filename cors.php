<?php
// Caminho: faz_bem_v2/cors.php
// Configurações de CORS para APIs quando o frontend e backend estão em servidores diferentes.

$origemRequisicao = $_SERVER['HTTP_ORIGIN'] ?? '';

// Lista de origens permitidas (produção)
$origensPermitidas = [
    'https://clubefazbem.duckdns.org',
    'http://clubefazbem.duckdns.org',
    'https://www.clubefazbem.com',
    'http://www.clubefazbem.com',
    'https://clubefazbem.com',
    'http://clubefazbem.com',
];

// INF-03: Origens de desenvolvimento apenas em ambiente local
require_once __DIR__ . '/app/Env.php';
Env::load(__DIR__ . '/.env');
$ambiente = $_ENV['AMBIENTE'] ?? 'producao';
if ($ambiente === 'local') {
    $origensPermitidas = array_merge($origensPermitidas, [
        'https://jxs-m.github.io',
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ]);
}

// Validação de origens permitidas
$permitido = false;

if ($origemRequisicao) {
    if (in_array($origemRequisicao, $origensPermitidas, true)) {
        $permitido = true;
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        // Permite se a origem corresponder ao próprio Host do servidor
        $host = $_SERVER['HTTP_HOST'];
        if ($origemRequisicao === "http://$host" || $origemRequisicao === "https://$host") {
            $permitido = true;
        }
    }
}

// Define o cabeçalho Access-Control-Allow-Origin se a origem for permitida
if ($permitido) {
    header("Access-Control-Allow-Origin: $origemRequisicao");
}

// Cabeçalho Vary: Origin é essencial quando Access-Control-Allow-Origin é dinâmico
header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Trata requisições preflight (OPTIONS) antes de iniciar a sessão
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Configurações de cookies de sessão seguros antes de qualquer session_start()
require_once __DIR__ . '/app/Security.php';
