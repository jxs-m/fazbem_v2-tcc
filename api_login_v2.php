<?php
// Caminho: faz_bem_v2/api_login_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Usuario.php';
require_once __DIR__ . '/app/Security.php';

Security::checkCSRF();
Security::checkRateLimit(10, 60);

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: $_POST;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido (' . $_SERVER['REQUEST_METHOD'] . '). Verifique se a URL está forçando redirecionamento (ex: de http para https).']);
    exit;
}

if (!$data || empty($data['email']) || empty($data['senha'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha e-mail e senha. Dados recebidos estavam vazios.']);
    exit;
}

try {
    $usuarioModel = new Usuario();
   
    $user = $usuarioModel->buscarPorEmail($data['email']);

    
    if ($user && password_verify($data['senha'], $user['senha'])) {
        session_regenerate_id(true);
        
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['nome'] = $user['nome'];
        $_SESSION['tipo_usuario'] = $user['tipo_usuario'];

        if ($user['tipo_usuario'] === 'admin') {
            $redirect = 'admin.html';
        } elseif ($user['tipo_usuario'] === 'entregador') {
            $redirect = 'entregador.html';
        } elseif ($user['tipo_usuario'] === 'separador') {
            $redirect = 'separador.html';
        } else {
            $redirect = 'perfil.html';
        }

        echo json_encode(['success' => true, 'redirect' => $redirect]);

    } else {
        echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor: ' . $e->getMessage()]);
}
?>