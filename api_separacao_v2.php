<?php
// Caminho: faz_bem_v2/api_separacao_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Database.php';

// Apenas admin ou separador
if (!isset($_SESSION['tipo_usuario']) || !in_array($_SESSION['tipo_usuario'], ['admin', 'separador'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = Database::getConexao();

try {
    if ($method === 'GET') {
        // Listar todos os pedidos 'Em separação'
        $sql = "SELECT p.id, p.data_pedido, p.obs_pontual, u.nome as cliente 
                FROM pedidos p 
                JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.status_entrega = 'Em separação' 
                ORDER BY p.id ASC";
        $pedidos = $pdo->query($sql)->fetchAll();

        foreach ($pedidos as &$ped) {
            // Buscar itens
            $sqlItens = "SELECT i.id as item_id, i.quantidade, i.preco_unitario, pr.nome, pr.unidade, pr.tipo_venda, pr.peso_estimado_g 
                         FROM itens_pedido i 
                         JOIN produtos pr ON i.produto_id = pr.id 
                         WHERE i.pedido_id = ?";
            $stmt = $pdo->prepare($sqlItens);
            $stmt->execute([$ped['id']]);
            $ped['itens'] = $stmt->fetchAll();

            $sqlPrefs = "SELECT tipo, descricao FROM preferencias WHERE usuario_id = (SELECT usuario_id FROM pedidos WHERE id = ?)";
            $stmtPrefs = $pdo->prepare($sqlPrefs);
            $stmtPrefs->execute([$ped['id']]);
            $ped['preferencias'] = $stmtPrefs->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'pedidos' => $pedidos]);
        exit;
    }

    if ($method === 'POST') {
        // Salvar a pesagem
        $data = json_decode(file_get_contents('php://input'), true);
        $pedido_id = $data['pedido_id'] ?? null;
        $itensPesados = $data['itens'] ?? []; // [{item_id: 1, quantidade_real: 2.5}, ...]

        if (!$pedido_id || empty($itensPesados)) {
            throw new Exception("Dados inválidos.");
        }

        $pdo->beginTransaction();

        $novoTotalPedido = 0;

        foreach ($itensPesados as $item) {
            $item_id = $item['item_id'];
            $q_real = floatval($item['quantidade_real']); // Pode ser 0 se estiver em falta
            
            // Buscar infos do item para calcular preço real
            $sqlInfo = "SELECT i.preco_unitario FROM itens_pedido i WHERE i.id = ?";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([$item_id]);
            $info = $stmtInfo->fetch();

            if ($info) {
                $preco_real = $q_real * floatval($info['preco_unitario']);
                $novoTotalPedido += $preco_real;

                // Atualizar item
                $sqlUpdate = "UPDATE itens_pedido SET quantidade_real = ?, preco_real = ? WHERE id = ?";
                $pdo->prepare($sqlUpdate)->execute([$q_real, $preco_real, $item_id]);
            }
        }

        $sqlPedUpdate = "UPDATE pedidos SET valor_total = ?, status_entrega = 'Aguardando Entrega', obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Pesado]') WHERE id = ?";
        $pdo->prepare($sqlPedUpdate)->execute([$novoTotalPedido, $pedido_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Pesagem registrada com sucesso! Pedido aguardando entrega.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
