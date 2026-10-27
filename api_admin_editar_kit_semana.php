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
require_once __DIR__ . '/app/Models/Producao.php';

try {
    $pdo = Database::getConexao();
    $pedidoModel = new Pedido();
    $prefModel = new Preferencia();

    $pdo->beginTransaction();

    // Passo 1: Encontrar todos os pedidos de assinatura ativos gerados esta semana que ainda estão "Em separação"
    $sqlPedidosSemana = "SELECT id FROM pedidos 
                         WHERE tipo_pedido = 'Assinatura' 
                         AND status_entrega = 'Em separação' 
                         AND status_pagamento != 'Cancelado'
                         AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)";
    $stmtPedidos = $pdo->query($sqlPedidosSemana);
    $pedidosExistentes = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // Passo 2: Cancelar e estornar os pedidos substituídos (preservando o histórico de pedidos e itens)
    if (!empty($pedidosExistentes)) {
        $ids = array_column($pedidosExistentes, 'id');
        foreach ($ids as $pedIdAntigo) {
            $pedidoModel->atualizarStatus(
                (int) $pedIdAntigo,
                'pagamento',
                'Cancelado',
                (int) $_SESSION['usuario_id'],
                'admin',
                'Substituição do Kit da Semana'
            );
        }
    }

    // Passo 3: Agora recriar com base nos novos selecionados para todos os assinantes ATIVOS
    $sqlAssinantes = "SELECT a.usuario_id, u.nome FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.status = 'Ativa'";
    $stmtAssinantes = $pdo->query($sqlAssinantes);
    $assinantes = $stmtAssinantes->fetchAll();

    $qtdGerados = 0;

    // Pre-fetch product info to avoid N+1 queries
    $infoProdutosCache = [];
    $idsProdKit = [];
    foreach ($produtosKit as $p) {
        if (isset($p['id']) && is_numeric($p['id'])) {
            $idsProdKit[] = intval($p['id']);
        }
    }
    if (!empty($idsProdKit)) {
        $placeholdersProds = implode(',', array_fill(0, count($idsProdKit), '?'));
        $sqlProds = "SELECT id, nome, unidade, tipo_venda, peso_estimado_g, ativo FROM produtos WHERE id IN ($placeholdersProds) AND ativo = 1";
        $stmtProds = $pdo->prepare($sqlProds);
        $stmtProds->execute($idsProdKit);
        while ($row = $stmtProds->fetch(PDO::FETCH_ASSOC)) {
            $infoProdutosCache[$row['id']] = $row;
        }
    }

    $sqlPedidoNovo = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, obs_pontual, estoque_reservado, semana_ativa_chave) 
                  VALUES (?, ?, 'Pendente', 'Em separação', 'Assinatura', ?, 0, CONCAT(?, '_', YEARWEEK(NOW(), 0)))";
    $stmtPedidoNovo = $pdo->prepare($sqlPedidoNovo);

    $sqlItemNovo = "INSERT INTO itens_pedido (pedido_id, produto_id, produto_nome, produto_unidade, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?, 0)";
    $stmtItemNovo = $pdo->prepare($sqlItemNovo);

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
            if (!isset($infoProdutosCache[$prod['id']])) {
                continue; // Prevent inserting fake or inactive product IDs
            }
            $nomeProd = trim($prod['nome'] ?? ($infoProdutosCache[$prod['id']]['nome'] ?? ''));
            if (!in_array($nomeProd, $exclusoes)) {
                $itensDoPedido[] = $prod;
            }
        }

        if (empty($itensDoPedido)) {
            continue;
        }

        $valorFixoSemanal = 25.00;
        
        $stmtPedidoNovo->execute([(int) $usuarioId, $valorFixoSemanal, $observacao, (int) $usuarioId]);
        $pedidoNovoId = (int) $pdo->lastInsertId();
        $itensParaReserva = [];

        foreach ($itensDoPedido as $item) {
            $qtd = isset($item['quantidade']) ? floatval($item['quantidade']) : 1.0;
            
            $prodInfo = $infoProdutosCache[$item['id']] ?? null;
            
            $estoqueDecremento = round(floatval(Producao::calcularEstoqueDecremento($qtd, $prodInfo, $item['unidade_escolhida'] ?? null)), 3);

            $stmtItemNovo->execute([
                $pedidoNovoId,
                (int) $item['id'],
                $prodInfo['nome'] ?? null,
                $prodInfo['unidade'] ?? null,
                $estoqueDecremento
            ]);
            $itensParaReserva[] = [
                'produto_id' => (int) $item['id'],
                'estoque_decremento' => $estoqueDecremento
            ];
        }

        $pedidoModel->reservarEstoqueItens($pedidoNovoId, $itensParaReserva, 'Kit Assinatura Editado');

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
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao editar kit da semana: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
