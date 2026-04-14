<?php
// Caminho: faz_bem_v2/api_admin_clientes_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Cliente.php';

// 1. Verificação de Segurança (Apenas administradores podem acessar)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $clienteModel = new Cliente();

    
    if ($method === 'GET') {
        $lista = $clienteModel->listarTodos();
        echo json_encode(['success' => true, 'data' => $lista]);
        exit;
    }

   
    if ($method === 'POST') {
        // Recebe os dados enviados pelo JavaScript
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id']) || empty($data['nome']) || empty($data['telefone'])) {
            throw new Exception("Dados obrigatórios incompletos.");
        }

        $endereco = $data['endereco'] ?? '';
        $frequencia = $data['frequencia'] ?? '';
        $status = $data['status'] ?? 'Ativa';

        $atualizou = $clienteModel->atualizar(
            $data['id'],
            $data['nome'],
            $data['telefone'],
            $endereco,
            $frequencia,
            $status
        );

        if ($atualizou) {
            echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso!']);
        } else {
            throw new Exception("Nenhuma alteração foi realizada.");
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    error_log("DB Error em admin clientes: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>