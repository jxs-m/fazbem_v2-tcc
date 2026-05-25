<?php
// Caminho: faz_bem_v2/api_gerenciar_assinatura_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Assinatura.php';

// Segurança: Apenas clientes logados podem gerir as assinaturas
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
            
            $valor_mensal = isset($assinaturaAtual['valor_mensal']) ? floatval($assinaturaAtual['valor_mensal']) : 100.00;
            $entregas_por_mes = ($frequencia === 'Quinzenal') ? 2 : 4;
            $valor_credito = round($valor_mensal / $entregas_por_mes, 2);
            
            $pdo = Database::getConexao();
            $pdo->beginTransaction();
            
            $sqlWallet = "UPDATE usuarios SET saldo_compensacao = saldo_compensacao + ? WHERE id = ?";
            $stmtWallet = $pdo->prepare($sqlWallet);
            $stmtWallet->execute([$valor_credito, $usuario_id]);
            
            $motivo = "Pausa na Assinatura (Compensação {$frequencia})";
            $sqlTrans = "INSERT INTO transacoes_financeiras (usuario_id, tipo, valor, motivo) VALUES (?, 'Credito', ?, ?)";
            $stmtTrans = $pdo->prepare($sqlTrans);
            $stmtTrans->execute([$usuario_id, $valor_credito, $motivo]);
            
            $pdo->commit();

            $valor_formatado = number_format($valor_credito, 2, ',', '.');
            $mensagem = "Entregas pausadas com sucesso. R$ {$valor_formatado} foram adicionados à sua carteira (Compensação).";
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
            if (empty($data['descricao'])) {
                throw new Exception("Descrição da preferência é obrigatória.");
            }
            require_once __DIR__ . '/app/Models/Preferencia.php';
            $prefModel = new Preferencia();
            $tipo = $data['tipo'] ?? 'Troca Fixa';
            $prefModel->adicionar($usuario_id, $tipo, $data['descricao']);
            echo json_encode(['success' => true, 'message' => 'Preferência salva com sucesso.']);
            exit;
        case 'remover_preferencia':
            if (empty($data['pref_id'])) throw new Exception("ID da preferência não informado.");
            require_once __DIR__ . '/app/Models/Preferencia.php';
            $prefModel = new Preferencia();
            $prefModel->remover($data['pref_id'], $usuario_id);
            echo json_encode(['success' => true, 'message' => 'Preferência removida.']);
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