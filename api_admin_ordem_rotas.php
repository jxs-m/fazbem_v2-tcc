<?php
// Caminho: faz_bem_v2/api_admin_ordem_rotas.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';

// Verificação de Segurança (Bloqueia quem não é admin)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    try {
        $pdo = Database::getConexao();
        $pdo->beginTransaction();

        $sql = "UPDATE pedidos SET ordem_entrega = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        foreach ($input as $item) {
            if (isset($item['id']) && isset($item['ordem'])) {
                $stmt->execute([intval($item['ordem']), intval($item['id'])]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!']);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de sistema: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>
