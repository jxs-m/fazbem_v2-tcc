<?php
// Caminho: faz_bem_v2/api_producao_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores.']);
    exit;
}

require_once __DIR__ . '/app/Models/Producao.php';

try {
    $producaoModel = new Producao();
    
    $totalKits = $producaoModel->obterContagemKitsSemana();
    $relatorioHortalicas = $producaoModel->gerarRelatorioHortalicas();

    echo json_encode([
        'success' => true,
        'kitsNaSemana' => $totalKits,
        'limiteMaximo' => 200,
        'hortalicasNecessarias' => $relatorioHortalicas
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o relatório de produção.']);
}
?>
