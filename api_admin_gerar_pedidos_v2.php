<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_admin_gerar_pedidos_v2.php

header('Content-Type: application/json');

Security::checkCSRF();

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['produtos_kit']) || !is_array($data['produtos_kit'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum produto selecionado para o kit.']);
    exit;
}

$produtosKit = $data['produtos_kit'];

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Pedido.php';
require_once __DIR__ . '/app/Models/Preferencia.php';

try {
    $pdo = Database::getConexao();
    $pedidoModel = new Pedido();
    $prefModel = new Preferencia();

    // Buscar assinantes ativos
    $sqlAssinantes = "SELECT a.usuario_id, u.nome FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.status = 'Ativa'";
    $stmtAssinantes = $pdo->query($sqlAssinantes);
    $assinantes = $stmtAssinantes->fetchAll();

    $qtdGerados = 0;

    // Pre-fetch product info to avoid N+1 queries
    $infoProdutosCache = [];
    $idsProdKit = array_map(function($p) { return intval($p['id']); }, $produtosKit);
    if (!empty($idsProdKit)) {
        $placeholdersProds = implode(',', array_fill(0, count($idsProdKit), '?'));
        $sqlProds = "SELECT id, unidade, tipo_venda, peso_estimado_g FROM produtos WHERE id IN ($placeholdersProds)";
        $stmtProds = $pdo->prepare($sqlProds);
        $stmtProds->execute($idsProdKit);
        while ($row = $stmtProds->fetch(PDO::FETCH_ASSOC)) {
            $infoProdutosCache[$row['id']] = $row;
        }
    }

    foreach ($assinantes as $assinante) {
        $usuarioId = $assinante['usuario_id'];

        
        if ($pedidoModel->verificarPedidoExistenteSemana($usuarioId)) {
            continue; 
        }

        
        $prefs = $prefModel->buscarPorUsuario($usuarioId);
        $exclusoes = [];
        $observacao = '';

        foreach ($prefs as $pref) {
            if ($pref['tipo'] === 'Troca Fixa') {
                
                $nomeExcluido = str_replace("Não consumo: ", "", $pref['descricao']);
                $exclusoes[] = trim($nomeExcluido);
            } elseif ($pref['tipo'] === 'Observação') {
                $observacao = $pref['descricao'];
            }
        }

         $itensDoPedido = [];
        foreach ($produtosKit as $prod) {
            $nomeProd = trim($prod['nome']);
            if (!in_array($nomeProd, $exclusoes)) {
                $itensDoPedido[] = $prod;
            }
        }

       if (empty($itensDoPedido)) {
            continue;
        }

        $pdo->beginTransaction();

        $valorFixoSemanal = 25.00;
        
        $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, obs_pontual) 
                      VALUES (?, ?, 'Pendente', 'Em separação', 'Assinatura', ?)";
        $stmtPedido = $pdo->prepare($sqlPedido);
        $stmtPedido->execute([$usuarioId, $valorFixoSemanal, $observacao]);
        $pedidoId = $pdo->lastInsertId();

        $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, 0)";
        $stmtItem = $pdo->prepare($sqlItem);

        foreach ($itensDoPedido as $item) {
            $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
            
            $prodInfo = $infoProdutosCache[$item['id']] ?? null;
            
            $estoqueDecremento = Producao::calcularEstoqueDecremento($qtd, $prodInfo, $item['unidade_escolhida'] ?? null);

            $stmtItem->execute([$pedidoId, $item['id'], $estoqueDecremento]);
        }

        $pdo->commit();
        $qtdGerados++;
    }

    echo json_encode(['success' => true, 'gerados' => $qtdGerados]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("DB Error ao gerar pedidos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de banco de dados.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado.']);
}
?>
