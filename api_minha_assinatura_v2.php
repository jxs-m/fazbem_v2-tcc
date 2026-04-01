<?php
// Caminho: faz_bem_v2/api_minha_assinatura_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Assinatura.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
}

try {
    $assinaturaModel = new Assinatura();
    $dados = $assinaturaModel->buscarPorUsuario($_SESSION['usuario_id']);
    
    echo json_encode(['success' => true, 'data' => $dados]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno.']);
}
?>