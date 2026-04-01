<?php
// api_teste_upload.php
header('Content-Type: application/json');


require_once __DIR__ . '/app/Models/Produto.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

try {
    // 1. Receber os dados de texto
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

    // 3. Validação do Upload
    if ($imagem['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro durante o upload do ficheiro. Código: " . $imagem['error']);
    }

    // Verifica a extensão do ficheiro para segurança (apenas imagens)
    $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!in_array($extensao, $permitidas)) {
        throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
    }

    // 4. Gerar um nome único para a imagem e definir o destino
    // Exemplo de nome gerado: 64a8b7c9f1e2d.jpg
    $novoNome = uniqid() . '.' . $extensao;
    
    // O caminho físico no servidor (onde o ficheiro será efetivamente gravado)
    $caminhoFisico = __DIR__ . '/uploads/' . $novoNome;
    
    // O caminho relativo que será gravado na base de dados (para ser acedido via HTML/URL)
    $caminhoBanco = 'uploads/' . $novoNome;

    // 5. Mover o ficheiro temporário para a pasta definitiva
    if (!move_uploaded_file($imagem['tmp_name'], $caminhoFisico)) {
        throw new Exception("Falha ao mover a imagem para a pasta uploads.");
    }

    // 6. Usar a Orientação a Objetos para guardar na Base de Dados
    $produtoModel = new Produto();
    $salvou = $produtoModel->salvar($nome, $categoria, $preco, $unidade, $estoque, $caminhoBanco);

    if ($salvou) {
        echo json_encode([
            'success' => true, 
            'message' => 'Upload e registo concluídos com sucesso!',
            'caminho' => $caminhoBanco // Retornamos o caminho para mostrar na tela
        ]);
    } else {
        throw new Exception("Erro ao guardar na base de dados.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>