<?php
// Caminho: faz_bem_v2/api_perfil_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Usuario.php';
require_once __DIR__ . '/app/Models/Pedido.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$usuario_id = $_SESSION['usuario_id'];

try {
    $usuarioModel = new Usuario();

    if ($method === 'GET') {
        $pedidoModel = new Pedido();

        $dadosUsuario = $usuarioModel->buscarPorId($usuario_id);
        
        $historicoPedidos = $pedidoModel->buscarPorUsuario($usuario_id);

        echo json_encode([
            'success' => true,
            'usuario' => $dadosUsuario,
            'pedidos' => $historicoPedidos
        ]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nome']) || empty($data['telefone']) || empty($data['endereco'])) {
            throw new Exception("Nome, telefone e endereço são obrigatórios.");
        }

        $novaSenhaHash = null;
        if (!empty($data['senha'])) {
            $novaSenhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        $referencia = $data['referencia'] ?? '';

        $atualizou = $usuarioModel->atualizarPerfil(
            $usuario_id, 
            $data['nome'], 
            $data['telefone'], 
            $data['endereco'], 
            $referencia, 
            $novaSenhaHash
        );

        if ($atualizou) {
            // Atualiza o nome na sessão caso ele tenha mudado
            $_SESSION['nome'] = $data['nome'];
            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!']);
        } else {
            throw new Exception("Nenhuma alteração foi realizada.");
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    error_log("DB Error no perfil: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>