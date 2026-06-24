<?php
// Caminho: faz_bem_v2/cors.php
// Configurações de CORS para APIs quando o frontend e backend estão em servidores diferentes.

// Adicione as URLs do seu frontend aqui (ex: seu GitHub Pages e localhost)
$origensPermitidas = [
    'https://seu-usuario.github.io', // Substitua pelo seu endereço do GitHub Pages
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:5500', // Portas comuns do Live Server (VS Code)
    'http://127.0.0.1:5500',
];

$origemRequisicao = $_SERVER['HTTP_ORIGIN'] ?? '';

// Permite conexões de origens cadastradas
if (in_array($origemRequisicao, $origensPermitidas)) {
    header("Access-Control-Allow-Origin: $origemRequisicao");
} else {
    // Em produção, evite usar '*' se for trafegar dados confidenciais com cookies.
    // Mas para testes rápidos ou APIs públicas, você pode descomentar a linha abaixo:
    // header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Trata requisições preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}
