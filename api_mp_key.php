<?php
// Caminho: faz_bem_v2/api_mp_key.php
require_once __DIR__ . '/app/Env.php';
Env::load(__DIR__ . '/.env');

header('Content-Type: application/json');

// Retorna apenas a Public Key, o Access Token NUNCA deve ser exposto aqui!
echo json_encode([
    'public_key' => getenv('MERCADO_PAGO_PUBLIC_KEY') ?: ''
]);
?>
