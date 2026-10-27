<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Models/Usuario.php';

header('Content-Type: application/json');

Security::checkCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

// Previne bruteforce no fluxo de recuperação de senha (5 tentativas por hora por IP)
Security::checkRateLimit(5, 3600);

$rawBody = file_get_contents('php://input');
if ($rawBody === '' && PHP_SAPI === 'cli') {
    $rawBody = file_get_contents('php://stdin');
}
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$acao = strtolower(trim((string) ($input['acao'] ?? '')));
$email = trim((string) ($input['email'] ?? ''));
$token = trim((string) ($input['token'] ?? ''));
$nova_senha = trim((string) ($input['nova_senha'] ?? ''));

try {
    $usuarioModel = new Usuario();

    // Etapa 1: Solicitação de recuperação (gera token CSPRNG, salva apenas o hash SHA-256 e envia ao canal verificado)
    if ($acao === 'solicitar' || ($token === '' && $nova_senha === '' && $email !== '')) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Informe um endereço de e-mail válido.']);
            exit;
        }

        $res = $usuarioModel->solicitarRecuperacaoSenha($email);
        http_response_code(200);
        echo json_encode($res);
        exit;
    }

    // Etapa 2: Redefinição mediante posse do token de uso único enviado ao canal verificado
    if ($token === '' || $nova_senha === '') {
        $usuarioModel->registrarAuditoriaSeguranca(
            null,
            'redefinir_senha_falha',
            $_SERVER['REMOTE_ADDR'] ?? null,
            'Tentativa de redefinição sem token de canal verificado'
        );
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'O código de recuperação enviado ao canal verificado e a nova senha são obrigatórios.']);
        exit;
    }

    if (strlen($nova_senha) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 8 caracteres.']);
        exit;
    }

    $usuarioModel->redefinirSenhaComToken($token, $nova_senha, $email !== '' ? $email : null);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Senha redefinida com sucesso. Todas as sessões anteriores foram encerradas.'
    ]);

} catch (Exception $e) {
    $code = (int) $e->getCode();
    if (!in_array($code, [400, 403, 429], true)) {
        $code = 400;
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
