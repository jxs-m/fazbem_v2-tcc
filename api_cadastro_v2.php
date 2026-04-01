<?php
// Caminho: faz_bem_v2/api_cadastro_v2.php
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Usuario.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    $usuarioModel = new Usuario();

   
    if ($usuarioModel->buscarPorEmail($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
        exit;
    }

   
    $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);

    
    $referencia = $data['referencia'] ?? null;
    
    $usuarioModel->cadastrarCliente(
        $data['nome'], 
        $data['email'], 
        $senhaHash, 
        $data['telefone'], 
        $data['endereco'], 
        $referencia, 
        $data['frequencia']
    );

    echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()]);
}
?>