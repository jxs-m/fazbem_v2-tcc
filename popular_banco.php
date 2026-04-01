<?php
// Caminho: faz_bem_v2/popular_banco.php
require_once __DIR__ . '/app/Database.php';

echo "<h1>Populando o Banco de Dados - Faz Bem V2</h1>";

try {
    $pdo = Database::getConexao();
    $pdo->beginTransaction();

    echo "<p>⏳ Iniciando inserção de dados...</p>";


    $senhaAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $senhaCliente = password_hash('cliente123', PASSWORD_DEFAULT);

    $sqlUser = "INSERT INTO usuarios (nome, email, telefone, endereco, ponto_referencia, senha, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtUser = $pdo->prepare($sqlUser);

    $usuarios = [
        ['Admin Faz Bem', 'admin2@fazbem.com', '(55) 99999-0000', 'Sede Faz Bem', '', $senhaAdmin, 'admin'],
        ['Carlos Silva', 'carlos@email.com', '(55) 98888-1111', 'Rua das Flores, 123', 'Casa verde', $senhaCliente, 'cliente'],
        ['Ana Pereira', 'ana@email.com', '(55) 97777-2222', 'Av. Principal, 45', 'Apto 302', $senhaCliente, 'cliente'],
        ['Marcos Souza', 'marcos@email.com', '(55) 96666-3333', 'Bairro Novo, 90', 'Perto da padaria', $senhaCliente, 'cliente']
    ];

    $idsClientes = []; // Array para guardar os IDs reais gerados pelo banco

    foreach ($usuarios as $u) {
        $stmtUser->execute($u);
        // Se for um cliente, guardamos o ID recém-criado
        if ($u[6] === 'cliente') {
            $idsClientes[] = $pdo->lastInsertId();
        }
    }
    echo "<p>✅ Usuários criados (Senhas: admin123 e cliente123).</p>";

    
    $sqlProduto = "INSERT INTO produtos (nome, categoria, preco, unidade, estoque_atual, imagem_url) VALUES (?, ?, ?, ?, ?, ?)";
    $stmtProduto = $pdo->prepare($sqlProduto);

    $produtos = [
        ['Maçã Gala', 'Frutas', 8.50, 'kg', 50, null],
        ['Banana Prata', 'Frutas', 6.00, 'kg', 40, null],
        ['Alface Crespa', 'Verduras', 3.50, 'un', 30, null],
        ['Rúcula Fresca', 'Verduras', 4.00, 'maço', 25, null],
        ['Cenoura', 'Legumes', 5.50, 'kg', 60, null],
        ['Batata Inglesa', 'Legumes', 7.00, 'kg', 100, null],
        ['Cebola Picada', 'Processados', 12.00, '500g', 15, null],
        ['Ovos Caipira', 'Outros', 18.00, 'dúzia', 20, null]
    ];

    foreach ($produtos as $p) {
        $stmtProduto->execute($p);
    }
    echo "<p>✅ Catálogo preenchido com 8 produtos variados.</p>";

    
    $sqlAssinatura = "INSERT INTO assinaturas (usuario_id, frequencia, status) VALUES (?, ?, ?)";
    $stmtAssinatura = $pdo->prepare($sqlAssinatura);
    
    
    $stmtAssinatura->execute([$idsClientes[0], 'Semanal', 'Ativa']);
    $stmtAssinatura->execute([$idsClientes[1], 'Quinzenal', 'Ativa']);

    $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, data_pedido) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))";
    $stmtPedido = $pdo->prepare($sqlPedido);
    
    $stmtPedido->execute([$idsClientes[0], 45.50, 'Pago', 'Entregue', 5]);
    $stmtPedido->execute([$idsClientes[0], 52.00, 'Pendente', 'Em separação', 0]);
    $stmtPedido->execute([$idsClientes[1], 110.00, 'Pago', 'Entregue', 2]);

    echo "<p>✅ Assinaturas e Pedidos gerados para o Dashboard com integridade referencial mantida.</p>";

    $pdo->commit();
    echo "<h2 style='color: green;'>🎉 Banco populado com sucesso! O sistema está pronto para a apresentação.</h2>";

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo "<h2 style='color: red;'>❌ Erro ao popular o banco:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>