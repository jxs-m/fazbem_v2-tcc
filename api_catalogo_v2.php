<?php
// Caminho: faz_bem_v2/api_catalogo_v2.php
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Produto.php';
require_once __DIR__ . '/app/Models/Producao.php';

try {
    $produtoModel = new Produto();
    $producaoModel = new Producao();
    
    $aberto = $producaoModel->catalogoAberto();
    $kitsDisponiveis = 200 - $producaoModel->obterContagemKitsSemana();

    if ($kitsDisponiveis < 0) $kitsDisponiveis = 0;

    $produtos = $produtoModel->listarTodos();

    echo json_encode([
        'success' => true, 
        'isOpen' => $aberto,
        'kitsDisponiveis' => $kitsDisponiveis,
        'data' => $produtos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o catálogo.']);
}
?>