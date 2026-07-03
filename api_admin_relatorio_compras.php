<?php
require_once __DIR__ . '/cors.php';

// Caminho: api_admin_relatorio_compras.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Producao.php';

try {
    $pdo = Database::getConexao();

    // Query para consolidar os itens dos pedidos em separação ou aguardando entrega desta semana
    $sql = "SELECT 
                p.id as produto_id,
                p.nome as produto_nome,
                p.unidade as unidade_padrao,
                p.tipo_venda,
                SUM(i.quantidade) as quantidade_total
            FROM itens_pedido i
            JOIN pedidos ped ON i.pedido_id = ped.id
            JOIN produtos p ON i.produto_id = p.id
            WHERE ped.status_entrega IN ('Em separação', 'Aguardando Entrega')
              AND YEARWEEK(ped.data_pedido, 0) = YEARWEEK(NOW(), 0)
            GROUP BY p.id
            ORDER BY p.nome ASC";

    $stmt = $pdo->query($sql);
    $itensDemandados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar a exibição
    $relatorio = [];
    foreach ($itensDemandados as $item) {
        $qtd = floatval($item['quantidade_total']);
        $exibicao = Producao::formatarExibicao($item['produto_nome'], $item['unidade_padrao'], $item['tipo_venda'], $qtd);

        $relatorio[] = [
            'produto_id' => $item['produto_id'],
            'produto_nome' => $item['produto_nome'],
            'tipo_venda' => $item['tipo_venda'],
            'quantidade_crua' => $qtd,
            'unidade_padrao' => $item['unidade_padrao'],
            'quantidade_formatada' => $exibicao
        ];
    }

    echo json_encode([
        'success' => true,
        'relatorio' => $relatorio
    ]);

} catch (PDOException $e) {
    error_log("DB Error no relatorio de compras: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do banco de dados ao gerar relatório.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
