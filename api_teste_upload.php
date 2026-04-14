<?php
// api_teste_upload.php
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();


require_once __DIR__ . '/app/Models/Produto.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

try {
    $nome = $_POST['nome'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $preco = $_POST['preco'] ?? 0;
    $unidade = $_POST['unidade'] ?? '';
    $estoque = $_POST['estoque'] ?? 0;

    // 2. Validação simples
    if (empty($nome) || empty($categoria) || !isset($_FILES['imagem'])) {
        throw new Exception("Dados incompletos ou imagem não enviada.");
    }

    $imagem = $_FILES['imagem'];

    if ($imagem['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro durante o upload do ficheiro. Código: " . $imagem['error']);
    }

    $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!in_array($extensao, $permitidas)) {
        throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
    }

    $novoNome = uniqid() . '.' . $extensao;
    
    $caminhoFisico = __DIR__ . '/uploads/' . $novoNome;
    
    $caminhoBanco = 'uploads/' . $novoNome;

    if (!move_uploaded_file($imagem['tmp_name'], $caminhoFisico)) {
        throw new Exception("Falha ao mover a imagem para a pasta uploads.");
    }

    $produtoModel = new Produto();
    $salvou = $produtoModel->salvar($nome, $categoria, $preco, $unidade, $estoque, $caminhoBanco);

    if ($salvou) {
        echo json_encode([
            'success' => true, 
            'message' => 'Upload e registo concluídos com sucesso!',
            'caminho' => $caminhoBanco 
        ]);
    } else {
        throw new Exception("Erro ao guardar na base de dados.");
    }

} catch (PDOException $e) {
    error_log("DB Error no upload: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar no banco de dados.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>