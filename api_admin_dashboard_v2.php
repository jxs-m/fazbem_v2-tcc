<?php
// Caminho: faz_bem_v2/api_admin_dashboard_v2.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Dashboard.php';

// Verificação de Segurança (Bloqueia quem não é admin)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403); // 403 Forbidden (Acesso Negado)
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores.']);
    exit;
}

try {
    // Instancia o Model e pega os dados
    $dashboardModel = new Dashboard();
    $resumo = $dashboardModel->getResumoGeral();

    // Retorna para o HTML (frontend)
    echo json_encode([
        'success' => true,
        'data' => $resumo
    ]);

} catch (Exception $e) {
    http_response_code(500); // 500 Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar métricas: ' . $e->getMessage()]);
}
?>