<?php
// Caminho: faz_bem_v2/api_estoque_v2.php
header('Content-Type: application/json');

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
        $qtde = (int)($input['quantidade'] ?? 0);
        $desc = $input['descricao'] ?? '';

        if (!$produto_id || !$tipo || $qtde <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }

        $pdo->beginTransaction();

        
        $sqlMov = "INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, descricao) VALUES (?, ?, ?, ?)";
        $stmtMov = $pdo->prepare($sqlMov);
        $stmtMov->execute([$produto_id, $tipo, $qtde, $desc]);

        
        $operador = ($tipo === 'Entrada') ? '+' : '-';
        $sqlUpd = "UPDATE produtos SET estoque_atual = estoque_atual $operador ? WHERE id = ?";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([$qtde, $produto_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' + $e->getMessage()]);
}
?>
