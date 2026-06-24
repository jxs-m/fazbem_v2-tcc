<?php
require_once __DIR__ . '/cors.php';

session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
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

    // Passo 2: Para cada pedido, devolver o estoque dos itens de forma unificada (Bulk Update)
    if (!empty($pedidosExistentes)) {
        $ids = array_column($pedidosExistentes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 1. Devolver estoque usando JOIN
        $sqlDevolver = "UPDATE produtos p
                        JOIN (
                            SELECT produto_id, SUM(quantidade) as total_qty
                            FROM itens_pedido
                            WHERE pedido_id IN ($placeholders)
                            GROUP BY produto_id
                        ) i ON p.id = i.produto_id
                        SET p.estoque_atual = p.estoque_atual + i.total_qty";
        $stmtDevolver = $pdo->prepare($sqlDevolver);
        $stmtDevolver->execute($ids);

        // 2. Deletar todos os itens_pedido relacionados
        $sqlDeleteItens = "DELETE FROM itens_pedido WHERE pedido_id IN ($placeholders)";
        $stmtDelItens = $pdo->prepare($sqlDeleteItens);
        $stmtDelItens->execute($ids);

        // 3. Deletar os pedidos
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

    $sqlEstoqueNovo = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
    $stmtEstoqueNovo = $pdo->prepare($sqlEstoqueNovo);

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
            
            $estoqueDecremento = $qtd;
            $unidade_escolhida = $item['unidade_escolhida'] ?? null;

            if ($prodInfo) {
                $unidade_banco = strtolower($prodInfo['unidade']);
                if ($prodInfo['tipo_venda'] === 'Fracionado') {
                    $baseGrams = null;
                    if (strpos($unidade_banco, 'kg') !== false) {
                        $baseGrams = 1000;
                    } elseif ($unidade_banco === 'g') {
                        $baseGrams = 1;
                    } elseif (strpos($unidade_banco, 'g') !== false) {
                        $num = intval($unidade_banco);
                        if ($num > 0) $baseGrams = $num;
                    }
                    
                    if ($baseGrams !== null) {
                        if ($unidade_escolhida === 'g') {
                            $estoqueDecremento = $qtd / $baseGrams;
                        } elseif ($unidade_escolhida === 'kg') {
                            $estoqueDecremento = ($qtd * 1000) / $baseGrams;
                        } else {
                            $estoqueDecremento = $qtd * $baseGrams;
                        }
                    } elseif ($unidade_escolhida === 'g' && strpos($unidade_banco, 'kg') !== false) {
                        $estoqueDecremento = $qtd / 1000;
                    } elseif ($unidade_escolhida === 'kg' && strpos($unidade_banco, 'g') !== false) {
                        $estoqueDecremento = $qtd * 1000;
                    }
                } else {
                    // Inteiro
                    if ($unidade_escolhida === 'un' || $unidade_escolhida === 'unidade') {
                        if (strpos($unidade_banco, 'kg') !== false && !empty($prodInfo['peso_estimado_g'])) {
                            $estoqueDecremento = ($qtd * $prodInfo['peso_estimado_g']) / 1000;
                        } elseif (strpos($unidade_banco, 'g') !== false && !empty($prodInfo['peso_estimado_g'])) {
                            $estoqueDecremento = $qtd * $prodInfo['peso_estimado_g'];
                        }
                    }
                }
            }

            $stmtItemNovo->execute([$pedidoNovoId, $item['id'], $estoqueDecremento]);
            $stmtEstoqueNovo->execute([$estoqueDecremento, $item['id']]);
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
