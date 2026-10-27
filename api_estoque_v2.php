<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_estoque_v2.php

header('Content-Type: application/json');

Security::checkCSRF();

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores.']);
    exit;
}

require_once __DIR__ . '/app/Database.php';

try {
    $pdo = Database::getConexao();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Retorna histórico
        $sql = "SELECT m.id, m.pedido_id, p.nome as produto, m.tipo, m.quantidade, m.descricao, m.data_movimentacao 
                FROM movimentacoes_estoque m
                JOIN produtos p ON m.produto_id = p.id
                ORDER BY m.id DESC LIMIT 50";
        $stmt = $pdo->query($sql);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === '' && PHP_SAPI === 'cli') {
            $rawBody = file_get_contents('php://stdin');
        }
        $input = json_decode($rawBody, true);
        
        $produto_id = filter_var($input['produto_id'] ?? null, FILTER_VALIDATE_INT);
        $tipo = $input['tipo'] ?? null; // Entrada, Saída, Descarte
        $rawQtde = $input['quantidade'] ?? null;
        $qtde = is_numeric($rawQtde) ? (float) $rawQtde : 0.0;
        $unidade_escolhida = $input['unidade_escolhida'] ?? null;
        $desc = trim((string) ($input['descricao'] ?? ''));

        if (!$produto_id || $produto_id <= 0 || !$tipo || $qtde <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos para movimentação de estoque.']);
            exit;
        }

        $rawStr = str_replace(',', '.', trim((string) $rawQtde));
        if (!preg_match('/^\d+(\.\d{1,3})?$/', $rawStr)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Precisão decimal inválida (máximo 3 casas decimais).']);
            exit;
        }

        // SEC-02: Whitelist explícita para prevenir SQL Injection via interpolação de operador
        $tiposPermitidos = ['Entrada', 'Saída', 'Descarte'];
        if (!in_array($tipo, $tiposPermitidos, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de movimentação inválido.']);
            exit;
        }

        $pdo->beginTransaction();

        $sqlProd = "SELECT id, nome, unidade, peso_estimado_g, estoque_atual FROM produtos WHERE id = ? FOR UPDATE";
        $stmtProd = $pdo->prepare($sqlProd);
        $stmtProd->execute([$produto_id]);
        $prodInfo = $stmtProd->fetch();

        if (!$prodInfo) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado.']);
            exit;
        }

        $estoqueIncremento = $qtde;

        if ($unidade_escolhida) {
            $unidade_banco = strtolower($prodInfo['unidade']);
            if ($unidade_escolhida === 'un' || $unidade_escolhida === 'unidade') {
                if (strpos($unidade_banco, 'kg') !== false && !empty($prodInfo['peso_estimado_g'])) {
                    $estoqueIncremento = ($qtde * $prodInfo['peso_estimado_g']) / 1000;
                } elseif (strpos($unidade_banco, 'g') !== false && !empty($prodInfo['peso_estimado_g'])) {
                    $estoqueIncremento = $qtde * $prodInfo['peso_estimado_g'];
                }
            } elseif ($unidade_escolhida === 'g' && strpos($unidade_banco, 'kg') !== false) {
                $estoqueIncremento = $qtde / 1000;
            } elseif ($unidade_escolhida === 'kg' && strpos($unidade_banco, 'g') !== false) {
                $estoqueIncremento = $qtde * 1000;
            }
        }

        $estoqueIncremento = round($estoqueIncremento, 3);
        if ($estoqueIncremento <= 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Quantidade convertida inválida.']);
            exit;
        }

        if ($tipo === 'Entrada') {
            $sqlUpd = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            $stmtUpd = $pdo->prepare($sqlUpd);
            $stmtUpd->execute([$estoqueIncremento, $produto_id]);
        } else {
            // UPDATE condicional para impedir estoque negativo em Saída ou Descarte
            $sqlUpd = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ? AND estoque_atual >= ?";
            $stmtUpd = $pdo->prepare($sqlUpd);
            $stmtUpd->execute([$estoqueIncremento, $produto_id, $estoqueIncremento]);

            if ($stmtUpd->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estoque insuficiente para realizar a baixa/descarte de ' . $prodInfo['nome'] . '.'
                ]);
                exit;
            }
        }

        $descCompleta = substr(trim("[$qtde $unidade_escolhida] " . $desc), 0, 255);

        $sqlMov = "INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, descricao) VALUES (?, ?, ?, ?)";
        $stmtMov = $pdo->prepare($sqlMov);
        $stmtMov->execute([$produto_id, $tipo, $estoqueIncremento, $descCompleta]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro no estoque: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>
