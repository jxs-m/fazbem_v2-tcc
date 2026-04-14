<?php
// Caminho: faz_bem_v2/api_gerenciar_assinatura_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Assinatura.php';

// Segurança: Apenas clientes logados podem gerir as suas assinaturas
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['acao'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação não especificada.']);
    exit;
}

try {
    $assinaturaModel = new Assinatura();
    $usuario_id = $_SESSION['usuario_id'];
    
    $assinaturaAtual = $assinaturaModel->buscarPorUsuario($usuario_id);
    
    $frequencia = $assinaturaAtual['frequencia'] ?? 'Semanal';
    $status = $assinaturaAtual['status'] ?? 'Pausada';

     switch ($data['acao']) {
        case 'pausar':
            $status = 'Pausada';
            $mensagem = 'Entregas pausadas com sucesso.';
            break;
        case 'reativar':
            $status = 'Ativa';
            $mensagem = 'Assinatura reativada! As entregas voltarão ao normal.';
            break;
        case 'cancelar':
            $status = 'Cancelada';
            $mensagem = 'Assinatura cancelada com sucesso.';
            break;
        case 'alterar_plano':
            if (empty($data['nova_frequencia'])) throw new Exception("Novo plano não informado.");
            $frequencia = $data['nova_frequencia'];
            $mensagem = 'Plano alterado para ' . $frequencia . ' com sucesso.';
            break;
        case 'nova_preferencia':
         echo json_encode(['success' => true, 'message' => 'Preferência salva.']);
            exit;
        default:
            throw new Exception("Ação desconhecida.");
    }

    $assinaturaModel->atualizar($usuario_id, $frequencia, $status);

    echo json_encode(['success' => true, 'message' => $mensagem]);

} catch (PDOException $e) {
    error_log("DB Error na assinatura: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>