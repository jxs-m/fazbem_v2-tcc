<?php
// Caminho: faz_bem_v2/api_catalogo_v2.php
if (ob_get_length()) ob_clean();
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
    $produtosFiltrados = [];

    foreach ($produtos as $p) {
        $p['dias_restantes'] = null;
        if (isset($p['temporario']) && (int)$p['temporario'] === 1 && !empty($p['duracao_dias'])) {
            $criado_ts = strtotime(date('Y-m-d', strtotime($p['criado_em'])));
            $hoje_ts = strtotime(date('Y-m-d'));
            $dias_passados = ($hoje_ts - $criado_ts) / 86400;
            $dias_restantes = (int)$p['duracao_dias'] - $dias_passados;
            
            if ($dias_restantes <= 0) {
                // Expirou, esconde do cliente
                continue;
            }
            $p['dias_restantes'] = $dias_restantes;
        }
        $produtosFiltrados[] = $p;
    }

    echo json_encode([
        'success' => true, 
        'isOpen' => $aberto,
        'kitsDisponiveis' => $kitsDisponiveis,
        'data' => $produtosFiltrados
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o catálogo.']);
}
?>