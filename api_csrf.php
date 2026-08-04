<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_csrf.php
header('Content-Type: application/json');

// só retorna o token
echo json_encode([
    'success' => true,
    'csrf_token' => Security::getCSRFToken()
]);
?>
