<?php
// Caminho: faz_bem_v2/api_admin_produtos_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Models/Produto.php';

// 1. Verificação de Segurança (Apenas administradores)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $produtoModel = new Produto();

    // ==========================================
    // REQUISIÇÃO GET: Listar todos os produtos na tabela
    // ==========================================
    if ($method === 'GET') {
        $produtos = $produtoModel->listarTodos();
        echo json_encode(['success' => true, 'data' => $produtos]);
        exit;
    }

    // ==========================================
    // REQUISIÇÃO POST: Cadastrar novo produto + Upload de Imagem
    // ==========================================
    if ($method === 'POST') {
        $nome = $_POST['nome'] ?? '';
        $categoria = $_POST['categoria'] ?? '';
        $preco = str_replace(',', '.', $_POST['preco'] ?? '0'); // Garante formato decimal
        $unidade = $_POST['unidade'] ?? '';
        $estoque = $_POST['estoque'] ?? 0;

        // Validação básica
        if (empty($nome) || empty($categoria) || !isset($_FILES['imagem'])) {
            throw new Exception("Dados incompletos ou imagem ausente.");
        }

        $imagem = $_FILES['imagem'];

        // Validação de erro no arquivo
        if ($imagem['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload da imagem. Código: " . $imagem['error']);
        }

        // Validação de segurança: aceitar apenas imagens reais
        $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($extensao, $permitidas)) {
            throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
        }

        // Gera nome único para evitar sobreposição (ex: 64a8b7c.jpg)
        $novoNome = uniqid() . '.' . $extensao;
        $caminhoFisico = __DIR__ . '/uploads/' . $novoNome;
        $caminhoBanco = 'uploads/' . $novoNome;

        // Move o arquivo da pasta temporária do servidor para a nossa pasta uploads
        if (!move_uploaded_file($imagem['tmp_name'], $caminhoFisico)) {
            throw new Exception("Falha ao salvar a imagem no servidor. Verifique as permissões da pasta.");
        }

        // Salva tudo no banco de dados usando a nossa classe
        $salvou = $produtoModel->salvar($nome, $categoria, $preco, $unidade, $estoque, $caminhoBanco);

        if ($salvou) {
            echo json_encode(['success' => true, 'message' => 'Produto cadastrado com sucesso!']);
        } else {
            throw new Exception("Erro ao salvar no banco de dados.");
        }
        exit;
    }

    // Se tentar usar PUT, DELETE, etc.
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>