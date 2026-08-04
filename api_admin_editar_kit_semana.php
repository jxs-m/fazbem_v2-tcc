<?php
require_once __DIR__ . '/cors.php';

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

    $pdo->beginTransaction();

    // Passo 1: Encontrar todos os pedidos de assinatura gerados esta semana que ainda estão "Em separação"
    $sqlPedidosSemana = "SELECT id FROM pedidos 
                         WHERE tipo_pedido = 'Assinatura' 
                         AND status_entrega = 'Em separação' 
                         AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)";
    $stmtPedidos = $pdo->query($sqlPedidosSemana);
    $pedidosExistentes = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // Passo 2: Para cada pedido, deletar itens e pedidos antigos de forma unificada
    if (!empty($pedidosExistentes)) {
        $ids = array_column($pedidosExistentes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 1. Deletar todos os itens_pedido relacionados
        $sqlDeleteItens = "DELETE FROM itens_pedido WHERE pedido_id IN ($placeholders)";
        $stmtDelItens = $pdo->prepare($sqlDeleteItens);
        $stmtDelItens->execute($ids);

        // 2. Deletar os pedidos
        $sqlDeletePedido = "DELETE FROM pedidos WHERE id IN ($placeholders)";
        $stmtDelPedido = $pdo->prepare($sqlDeletePedido);
        $stmtDelPedido->execute($ids);
    }

    // Passo 3: Agora recriar com base nos novos selecionados para todos os assinantes ATIVOS
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

    $sqlPedidoNovo = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, obs_pontual) 
                  VALUES (?, ?, 'Pendente', 'Em separação', 'Assinatura', ?)";
    $stmtPedidoNovo = $pdo->prepare($sqlPedidoNovo);

    $sqlItemNovo = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, 0)";
    $stmtItemNovo = $pdo->prepare($sqlItemNovo);

    foreach ($assinantes as $assinante) {
        $usuarioId = $assinante['usuario_id'];

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
            if (!isset($infoProdutosCache[$prod['id']])) {
                continue; // Prevent inserting fake product IDs
            }
            if (!in_array($nomeProd, $exclusoes)) {
                $itensDoPedido[] = $prod;
            }
        }

        if (empty($itensDoPedido)) {
            continue;
        }

        $valorFixoSemanal = 25.00;
        
        $stmtPedidoNovo->execute([$usuarioId, $valorFixoSemanal, $observacao]);
        $pedidoNovoId = $pdo->lastInsertId();

        foreach ($itensDoPedido as $item) {
            $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
            
            $prodInfo = $infoProdutosCache[$item['id']] ?? null;
            
            $estoqueDecremento = Producao::calcularEstoqueDecremento($qtd, $prodInfo, $item['unidade_escolhida'] ?? null);

            $stmtItemNovo->execute([$pedidoNovoId, $item['id'], $estoqueDecremento]);
        }

        $qtdGerados++;
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'gerados' => $qtdGerados, 'removidos' => count($pedidosExistentes)]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("DB Error ao editar kit da semana: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de banco de dados.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado.']);
}
