<?php
// Caminho: faz_bem_v2/api_admin_funcionarios_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Usuario.php';

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
        $sql = "SELECT id, nome, email, telefone, tipo_usuario FROM usuarios WHERE tipo_usuario IN ('admin', 'separador', 'entregador') ORDER BY tipo_usuario, nome";
        $stmt = $pdo->query($sql);
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $funcionarios]);
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

        $tipo = in_array($data['tipo_usuario'], ['admin', 'separador', 'entregador']) ? $data['tipo_usuario'] : 'entregador';

        $criou = $usuarioModel->cadastrarMembroEquipe($data['nome'], $data['email'], $senhaHash, $telefone, $tipo);

        if ($criou) {
            echo json_encode(['success' => true, 'message' => 'Funcionário cadastrado com sucesso!']);
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
        
        // Prevenir autoexclusão
        if ($data['id'] == $_SESSION['usuario_id']) {
             throw new Exception("Você não pode excluir sua própria conta.");
        }

        $pdo = Database::getConexao();
        $sql = "DELETE FROM usuarios WHERE id = ? AND tipo_usuario IN ('admin', 'separador', 'entregador')";
        $stmt = $pdo->prepare($sql);
        $deletou = $stmt->execute([$data['id']]);
        
        if ($deletou) {
            echo json_encode(['success' => true, 'message' => 'Funcionário excluído com sucesso!']);
        } else {
             throw new Exception("Erro ao excluir funcionário.");
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
