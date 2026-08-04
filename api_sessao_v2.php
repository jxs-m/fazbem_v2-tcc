<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_sessao_v2.php

header('Content-Type: application/json');

if (isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => true,
        'logado' => true,
        'usuario' => [
            'nome' => $_SESSION['nome'],
            'tipo' => $_SESSION['tipo_usuario']
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'logado' => false
    ]);
}
?>
