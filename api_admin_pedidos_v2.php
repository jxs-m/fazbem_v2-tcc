<?php
// Caminho: faz_bem_v2/api_admin_pedidos_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');

// Puxa o nosso Model recém-criado
require_once __DIR__ . '/app/Models/Pedido.php';

// 1. Verificação de Segurança (Bloqueia quem não é admin)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pedidoModel = new Pedido();

   
    if ($method === 'GET') {
        
        // Se vier um ID na URL (ex: api_admin_pedidos_v2.php?id=5), busca os detalhes para o Modal
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            // Busca as informações gerais (Pedido + Cliente)
            $info = $pedidoModel->buscarDetalhes($id);
            
            if (!$info) {
                throw new Exception("Pedido não encontrado.");
            }

            // Busca os itens que compõem esse pedido
            $itens = $pedidoModel->buscarItens($id);

            // Devolve tudo empacotado para o frontend montar a Folha de Separação
            echo json_encode([
                'success' => true,
                'info' => $info,
                'itens' => $itens
            ]);
            exit;
        } 
        
        // Se não vier ID, lista a tabela inteira do painel
        else {
            $lista = $pedidoModel->listarTodos();
            echo json_encode([
                'success' => true, 
                'data' => $lista
            ]);
            exit;
        }
    }

    
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id']) || empty($data['tipo']) || empty($data['valor'])) {
            throw new Exception("Dados incompletos para atualização.");
        }

        // Usa a Orientação a Objetos para atualizar o status
        $atualizou = $pedidoModel->atualizarStatus($data['id'], $data['tipo'], $data['valor']);

        if ($atualizou) {
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
        } else {
            throw new Exception("Nenhuma alteração foi feita.");
        }
        exit;
    }

    // Se chegar aqui, o método HTTP não é suportado
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>