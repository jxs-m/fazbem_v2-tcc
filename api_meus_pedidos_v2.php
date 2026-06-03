<?php
// Caminho: faz_bem_v2/api_meus_pedidos_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Pedido.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']); exit;
}

try {
    $pedidoModel = new Pedido();
    $acao = $_GET['acao'] ?? '';

    if ($acao === 'pedido_semana') {
        $pedido = $pedidoModel->buscarPedidoSemana($_SESSION['usuario_id']);
        if ($pedido) {
            $itens = $pedidoModel->buscarItens($pedido['id']);
            echo json_encode(['success' => true, 'pedido' => $pedido, 'itens' => $itens]);
        } else {
            echo json_encode(['success' => true, 'pedido' => null, 'itens' => []]);
        }
        exit;
    }

    if ($acao === 'ultimo_pedido') {
        $sql = "SELECT id FROM pedidos 
                WHERE usuario_id = ? 
                AND YEARWEEK(data_pedido, 0) < YEARWEEK(NOW(), 0)
                ORDER BY data_pedido DESC LIMIT 1";
        $stmt = $pedidoModel->pdo->prepare($sql);
        $stmt->execute([$_SESSION['usuario_id']]);
        $ultimo = $stmt->fetch();
        if ($ultimo) {
            $itens = $pedidoModel->buscarItens($ultimo['id']);
            echo json_encode(['success' => true, 'itens' => $itens]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nenhum pedido de semanas anteriores foi encontrado.']);
        }
        exit;
    }

    $pedidos = $pedidoModel->buscarPorUsuario($_SESSION['usuario_id']);

    echo json_encode(['success' => true, 'data' => $pedidos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar pedidos.']);
}
?>