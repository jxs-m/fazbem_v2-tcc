<?php
// api_teste_listar.php
header('Content-Type: application/json');

// Chamamos a nossa classe Produto
require_once __DIR__ . '/app/Models/Produto.php';

try {
    // Instancia o objeto Produto
    $produtoModel = new Produto();
    
    // Usa o método que criámos para buscar tudo na base de dados
    $produtos = $produtoModel->listarTodos();

    // Devolve os dados com sucesso
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