<?php
// Caminho: faz_bem_v2/api_config.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Security.php';

try {
    $pdo = Database::getConexao();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT chave, valor FROM configuracoes";
        $stmt = $pdo->query($sql);
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna um array associativo ['chave' => 'valor']
        
        echo json_encode(['success' => true, 'data' => $configs]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::checkCSRF();
        // Apenas admin pode alterar
        if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Nenhum dado enviado.']);
            exit;
        }

        $sql = "INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt = $pdo->prepare($sql);

        foreach ($input as $chave => $valor) {
            $stmt->execute([$chave, $valor]);
        }

        echo json_encode(['success' => true, 'message' => 'Configurações atualizadas.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
