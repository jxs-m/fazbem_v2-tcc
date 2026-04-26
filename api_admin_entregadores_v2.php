<?php
// Caminho: faz_bem_v2/api_admin_entregadores_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Usuario.php';

// 1. Verificação de Segurança (Apenas administradores podem acessar)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $usuarioModel = new Usuario();

    if ($method === 'GET') {
        $pdo = Database::getConexao();
        $sql = "SELECT id, nome, email, telefone FROM usuarios WHERE tipo_usuario = 'entregador'";
        $stmt = $pdo->query($sql);
        $entregadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $entregadores]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
            throw new Exception("Dados obrigatórios incompletos.");
        }

        // Validação de e-mail já existente
        if ($usuarioModel->buscarPorEmail($data['email'])) {
            echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
            exit;
        }

        $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $telefone = $data['telefone'] ?? '00000000000';

        $criou = $usuarioModel->cadastrarMembroEquipe($data['nome'], $data['email'], $senhaHash, $telefone, 'entregador');

        if ($criou) {
            echo json_encode(['success' => true, 'message' => 'Entregador cadastrado com sucesso!']);
        } else {
            throw new Exception("Erro ao salvar entregador no banco.");
        }
        exit;
    }
    
    if ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            throw new Exception("ID não fornecido.");
        }
        
        $pdo = Database::getConexao();
        $sql = "DELETE FROM usuarios WHERE id = ? AND tipo_usuario = 'entregador'";
        $stmt = $pdo->prepare($sql);
        $deletou = $stmt->execute([$data['id']]);
        
        if ($deletou) {
            echo json_encode(['success' => true, 'message' => 'Entregador excluído com sucesso!']);
        } else {
             throw new Exception("Erro ao excluir entregador.");
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    error_log("DB Error em admin entregadores: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
