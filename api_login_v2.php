<?php
// Caminho: faz_bem_v2/api_login_v2.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Usuario.php';
require_once __DIR__ . '/app/Security.php';

Security::checkRateLimit(10, 60);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['email']) || empty($data['senha'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha e-mail e senha.']);
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

       
        $redirect = ($user['tipo_usuario'] === 'admin') ? 'admin.html' : 'perfil.html';

        echo json_encode(['success' => true, 'redirect' => $redirect]);

    } else {
        echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor.']);
}
?>