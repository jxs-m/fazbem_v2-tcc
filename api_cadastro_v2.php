<?php
// Caminho: faz_bem_v2/api_cadastro_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Models/Usuario.php';
require_once __DIR__ . '/app/Security.php';

Security::checkRateLimit(5, 120);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    $usuarioModel = new Usuario();

    require_once __DIR__ . '/app/Validator.php';

    if (!empty($data['cpf']) && !Validator::validarCPF($data['cpf'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O CPF informado é inválido.']);
        exit;
    }

    if ($usuarioModel->buscarPorEmail($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
        exit;
    }

   
    $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);

    
    $referencia = $data['referencia'] ?? null;
    $lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
    $lng = isset($data['longitude']) ? (float)$data['longitude'] : null;
    $cpf = $data['cpf'] ?? null;
    
    $usuarioId = $usuarioModel->cadastrarCliente(
        $data['nome'], 
        $data['email'], 
        $senhaHash, 
        $data['telefone'], 
        $data['endereco'], 
        $referencia, 
        $data['frequencia'],
        $lat,
        $lng,
        $cpf
    );

    require_once __DIR__ . '/app/Models/Preferencia.php';
    $prefModel = new Preferencia();

    if (!empty($data['exclusoes']) && is_array($data['exclusoes'])) {
        foreach ($data['exclusoes'] as $produtoNome) {
            $prefModel->adicionar($usuarioId, 'Troca Fixa', "Não consumo: " . $produtoNome);
        }
    }

    if (!empty($data['observacao'])) {
        $prefModel->adicionar($usuarioId, 'Observação', $data['observacao']);
    }

    echo json_encode(['success' => true, 'message' => 'Cadastro realizado com sucesso!']);

} catch (PDOException $e) {
    error_log("DB Error no cadastro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de banco de dados. Tente novamente.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>