<?php
// api_teste_listar.php
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Produto.php';

try {
    $produtoModel = new Produto();
    
    $produtos = $produtoModel->listarTodos();

    echo json_encode([
        'success' => true, 
        'data' => $produtos
    ]);

} catch (PDOException $e) {
    error_log("DB Error em listar: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>