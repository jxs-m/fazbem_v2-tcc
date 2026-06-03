<?php
// Caminho: faz_bem_v2/api_estoque_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Security.php';
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
        $sql = "SELECT m.id, p.nome as produto, m.tipo, m.quantidade, m.descricao, m.data_movimentacao 
                FROM movimentacoes_estoque m
                JOIN produtos p ON m.produto_id = p.id
                ORDER BY m.id DESC LIMIT 50";
        $stmt = $pdo->query($sql);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $produto_id = $input['produto_id'] ?? null;
        $tipo = $input['tipo'] ?? null; // Entrada, Saída, Descarte
        $qtde = (float)($input['quantidade'] ?? 0);
        $unidade_escolhida = $input['unidade_escolhida'] ?? null;
        $desc = $input['descricao'] ?? '';

        if (!$produto_id || !$tipo || $qtde <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $pdo->beginTransaction();

        $sqlProd = "SELECT unidade, peso_estimado_g FROM produtos WHERE id = ?";
        $stmtProd = $pdo->prepare($sqlProd);
        $stmtProd->execute([$produto_id]);
        $prodInfo = $stmtProd->fetch();

        $estoqueIncremento = $qtde;

        if ($prodInfo && $unidade_escolhida) {
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

        $descCompleta = trim("[$qtde $unidade_escolhida] " . $desc);

        $sqlMov = "INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, descricao) VALUES (?, ?, ?, ?)";
        $stmtMov = $pdo->prepare($sqlMov);
        $stmtMov->execute([$produto_id, $tipo, $estoqueIncremento, $descCompleta]);

        $operador = ($tipo === 'Entrada') ? '+' : '-';
        $sqlUpd = "UPDATE produtos SET estoque_atual = estoque_atual $operador ? WHERE id = ?";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([$estoqueIncremento, $produto_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
