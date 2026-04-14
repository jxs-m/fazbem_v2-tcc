<?php
// Caminho: faz_bem_v2/api_admin_dashboard_v2.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Dashboard.php';

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403); // 403 Forbidden (Acesso Negado)
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores.']);
    exit;
}

try {
    $dashboardModel = new Dashboard();
    $resumo = $dashboardModel->getResumoGeral();

    echo json_encode([
        'success' => true,
        'data' => $resumo
    ]);

} catch (PDOException $e) {
    error_log("DB Error no dashboard: " . $e->getMessage());
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar métricas (Erro Interno).']);
} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>