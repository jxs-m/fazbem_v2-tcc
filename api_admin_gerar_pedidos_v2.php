<?php
// Caminho: faz_bem_v2/api_admin_gerar_pedidos_v2.php
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

    // Buscar assinantes ativos
    $sqlAssinantes = "SELECT a.usuario_id, u.nome FROM assinaturas a JOIN usuarios u ON a.usuario_id = u.id WHERE a.status = 'Ativa'";
    $stmtAssinantes = $pdo->query($sqlAssinantes);
    $assinantes = $stmtAssinantes->fetchAll();

    $qtdGerados = 0;

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

        $sqlEstoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
        $stmtEstoque = $pdo->prepare($sqlEstoque);

        foreach ($itensDoPedido as $item) {
            $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
            
            // Buscar informações do produto para saber se é fracionado ou tem conversão
            $sqlProd = "SELECT unidade, tipo_venda, peso_estimado_g FROM produtos WHERE id = ?";
            $stmtProd = $pdo->prepare($sqlProd);
            $stmtProd->execute([$item['id']]);
            $prodInfo = $stmtProd->fetch();
            
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

            $stmtItem->execute([$pedidoId, $item['id'], $estoqueDecremento]);
            $stmtEstoque->execute([$estoqueDecremento, $item['id']]);
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
