<?php
// Caminho: faz_bem_v2/api_catalogo_v2.php
header('Content-Type: application/json');

// Puxa o Model de Produtos que já está pronto e testado
require_once __DIR__ . '/app/Models/Produto.php';

try {
    $produtoModel = new Produto();
    
    // Busca todos os produtos na base de dados
    $produtos = $produtoModel->listarTodos();

    // Devolve para o front-end
    echo json_encode([
        'success' => true, 
        'data' => $produtos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o catálogo.']);
}
?>