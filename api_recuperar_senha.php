<?php
require_once __DIR__ . '/app/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$telefone = preg_replace('/\D/', '', $input['telefone'] ?? '');
$nova_senha = trim($input['nova_senha'] ?? '');

if (empty($email) || empty($telefone) || empty($nova_senha)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}

if (strlen($nova_senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres.']);
    exit;
}

try {
    $pdo = Database::getConexao();

    // Remove non-numeric characters from the database phone numbers for a reliable match
    $stmt = $pdo->prepare("SELECT id, telefone FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'E-mail ou telefone incorretos.']);
        exit;
    }

    $dbTelefone = preg_replace('/\D/', '', $user['telefone']);
    
    if ($dbTelefone !== $telefone) {
        echo json_encode(['success' => false, 'message' => 'E-mail ou telefone incorretos.']);
        exit;
    }

    $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $upd->execute([$senhaHash, $user['id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
