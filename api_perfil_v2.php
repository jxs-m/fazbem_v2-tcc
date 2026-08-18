<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_perfil_v2.php

header('Content-Type: application/json');

Security::checkCSRF();
Security::checkRateLimit(30, 60);

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
        require_once __DIR__ . '/app/Models/Preferencia.php';
        $prefModel = new Preferencia();

        $dadosUsuario = $usuarioModel->buscarPorId($usuario_id);
        
        $historicoPedidos = $pedidoModel->buscarPorUsuario($usuario_id);
        $preferencias = $prefModel->buscarPorUsuario($usuario_id);

        echo json_encode([
            'success' => true,
            'usuario' => $dadosUsuario,
            'pedidos' => $historicoPedidos,
            'preferencias' => $preferencias
        ]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $nome = trim($data['nome'] ?? '');
        $telefone = trim($data['telefone'] ?? '');
        $endereco = trim($data['endereco'] ?? '');

        if (empty($nome) || empty($telefone) || empty($endereco)) {
            throw new Exception("Nome, telefone e endereço são obrigatórios.");
        }

        if (mb_strlen($nome) > 100 || mb_strlen($telefone) > 20 || mb_strlen($endereco) > 255) {
            throw new Exception("Campos com tamanho inválido.");
        }

        $novaSenhaHash = null;
        if (!empty($data['senha'])) {
            if (strlen($data['senha']) < 8) {
                throw new Exception("A nova senha deve ter no mínimo 8 caracteres.");
            }
            $novaSenhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        $referencia = trim($data['referencia'] ?? '');
        $cpf = !empty($data['cpf']) ? trim($data['cpf']) : null;

        require_once __DIR__ . '/app/Validator.php';
        if (!empty($cpf) && !Validator::validarCPF($cpf)) {
            throw new Exception("O CPF informado é inválido.");
        }

        $atualizou = $usuarioModel->atualizarPerfil(
            $usuario_id, 
            $data['nome'], 
            $data['telefone'], 
            $cpf,
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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>