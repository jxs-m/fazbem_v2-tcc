<?php
// Caminho: faz_bem_v2/api_sessao_v2.php
session_start();
if (ob_get_length()) ob_clean();
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
