<?php
// Caminho: faz_bem_v2/api_admin_produtos_v2.php
session_start();
ob_clean();
header('Content-Type: application/json');
require_once __DIR__ . '/app/Security.php';
Security::checkCSRF();

require_once __DIR__ . '/app/Models/Produto.php';

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $produtoModel = new Produto();

   
    if ($method === 'GET') {
        $produtos = $produtoModel->listarTodos();
        echo json_encode(['success' => true, 'data' => $produtos]);
        exit;
    }

    if ($method === 'POST') {
        $id = $_POST['id'] ?? ''; // Se vier ID, é edição. Se não vier, é novo.
        $nome = $_POST['nome'] ?? '';
        $categoria = $_POST['categoria'] ?? '';
        $preco = str_replace(',', '.', $_POST['preco'] ?? '0');
        $unidade = $_POST['unidade'] ?? '';
        $estoque = $_POST['estoque'] ?? 0;
        $peso_estimado_g = $_POST['peso_estimado_g'] ?? 0;

        
        if (empty($nome) || empty($categoria)) {
            throw new Exception("Nome e categoria são obrigatórios.");
        }

        $caminhoBanco = null;

        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $imagem = $_FILES['imagem'];
            
            $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($extensao, $permitidas)) {
                throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
            }

            $novoNome = uniqid() . '.' . $extensao;
            $caminhoFisico = __DIR__ . '/uploads/' . $novoNome;
            $caminhoBanco = 'uploads/' . $novoNome;

            if (!move_uploaded_file($imagem['tmp_name'], $caminhoFisico)) {
                throw new Exception("Falha ao guardar a imagem no servidor.");
            }
        } else if (empty($id)) {
            // Se for um produto novo (sem ID), a imagem é estritamente obrigatória
            throw new Exception("A imagem é obrigatória para novos produtos.");
        }

         if (!empty($id)) {
            $sucesso = $produtoModel->atualizar($id, $nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoBanco);
            $mensagem = 'Produto atualizado com sucesso!';
        } else {
            $sucesso = $produtoModel->salvar($nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoBanco);
            $mensagem = 'Produto cadastrado com sucesso!';
        }

        if ($sucesso) {
            echo json_encode(['success' => true, 'message' => $mensagem]);
        } else {
            throw new Exception("Erro ao processar na base de dados.");
        }
        exit;
    }

    // Se tentar usar PUT, DELETE, etc.
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);

} catch (PDOException $e) {
    error_log("DB Error em admin produtos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno de bd.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro inesperado. Tente novamente.']);
}
?>