<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_minha_assinatura_v2.php

header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Assinatura.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
}

try {
    $assinaturaModel = new Assinatura();
    $dados = $assinaturaModel->buscarPorUsuario($_SESSION['usuario_id']);
    
    // Verificar se existe fatura pendente de mensalidade de assinatura
    require_once __DIR__ . '/app/Database.php';
    $pdo = Database::getConexao();
    $stmtFat = $pdo->prepare("SELECT id, mes_referencia, valor_mensalidade, valor_extras, valor_total, status FROM faturas_mensais WHERE usuario_id = ? AND status = 'Pendente' AND valor_mensalidade > 0 ORDER BY id DESC LIMIT 1");
    $stmtFat->execute([$_SESSION['usuario_id']]);
    $faturaPendente = $stmtFat->fetch();

    $possuiFaturaPendente = !empty($faturaPendente);
    $podeFazerPedidos = ($dados && $dados['status'] === 'Ativa' && !$possuiFaturaPendente);

    echo json_encode([
        'success' => true, 
        'data' => $dados,
        'fatura_pendente' => $faturaPendente,
        'possui_fatura_pendente' => $possuiFaturaPendente,
        'pode_fazer_pedidos' => $podeFazerPedidos
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno.']);
}
?>