<?php
// Caminho: faz_bem_v2/simular_pedidos.php
require_once __DIR__ . '/app/Database.php';

echo "<h1>Simulando Pedidos dos Clientes</h1>";

try {
    $pdo = Database::getConexao();
    $pdo->beginTransaction();

    // 1. Limpar pedidos e itens antigos para ter dados controlados
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE itens_pedido");
    $pdo->exec("TRUNCATE TABLE pedidos");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p>🧹 Pedidos e itens antigos limpos.</p>";

    // 2. Buscar clientes e produtos
    $clientes = $pdo->query("SELECT id, nome, endereco FROM usuarios WHERE tipo_usuario = 'cliente' ORDER BY id ASC")->fetchAll();
    $produtos = $pdo->query("SELECT id, nome, preco, unidade, tipo_venda FROM produtos ORDER BY id ASC")->fetchAll();

    if (empty($clientes) || empty($produtos)) {
        throw new Exception("Clientes ou produtos não encontrados. Execute popular_real.php primeiro.");
    }

    // 3. Simular pedidos com diferentes status
    // Pedidos a criar: array de [cliente_index, status_entrega, status_pagamento, tipo_pedido, itens: [prod_index => qtd]]
    $simulacoes = [
        // Natasha Frasson Pavin (Em separação)
        [
            'cliente_index' => 1, 
            'status_entrega' => 'Em separação',
            'status_pagamento' => 'Pendente',
            'tipo_pedido' => 'Assinatura',
            'itens' => [
                2 => 1.0, // Alface Crespa (1 un)
                3 => 2.0  // Rúcula Fresca (2 maços)
            ],
            'obs' => 'Troca tempero por rúcula'
        ],
        // Lila Tellechea Pinto (Aguardando Entrega)
        [
            'cliente_index' => 0, 
            'status_entrega' => 'Aguardando Entrega',
            'status_pagamento' => 'Pago',
            'tipo_pedido' => 'Assinatura',
            'itens' => [
                0 => 1.5, // Maçã Gala (1.5 kg)
                4 => 1.0  // Cenoura (1 kg)
            ],
            'obs' => ''
        ],
        // Luiz Eduardo Medaglia (Saiu para entrega)
        [
            'cliente_index' => 2, 
            'status_entrega' => 'Saiu para entrega',
            'status_pagamento' => 'Pago',
            'tipo_pedido' => 'Avulso',
            'itens' => [
                1 => 0.8, // Banana Prata (0.8 kg)
                5 => 2.0  // Batata Inglesa (2 kg)
            ],
            'obs' => 'Entregar no portão lateral'
        ],
        // Mauricio Lima Fontoura (Entregue)
        [
            'cliente_index' => 3, 
            'status_entrega' => 'Entregue',
            'status_pagamento' => 'Pago',
            'tipo_pedido' => 'Assinatura',
            'itens' => [
                2 => 2.0, // Alface
                4 => 0.5  // Cenoura
            ],
            'obs' => ''
        ],
        // Carla Uhmann (Em separação)
        [
            'cliente_index' => 6, 
            'status_entrega' => 'Em separação',
            'status_pagamento' => 'Pendente',
            'tipo_pedido' => 'Assinatura',
            'itens' => [
                2 => 4.0, // 4 Alfaces
                3 => 2.0, // 2 Rúculas
                5 => 1.5  // 1.5 kg Batata
            ],
            'obs' => '4 alfaces e 2 rúculas, o resto normal'
        ],
        // Fabiana de Moura Rubim (Aguardando Entrega)
        [
            'cliente_index' => 7, 
            'status_entrega' => 'Aguardando Entrega',
            'status_pagamento' => 'Pago',
            'tipo_pedido' => 'Avulso',
            'itens' => [
                0 => 2.0, // Maçã Gala (2 kg)
                1 => 1.2  // Banana Prata (1.2 kg)
            ],
            'obs' => ''
        ]
    ];

    $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, obs_pontual, data_pedido, entregue_em) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtPedido = $pdo->prepare($sqlPedido);

    $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, quantidade_real, preco_unitario, preco_real) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmtItem = $pdo->prepare($sqlItem);

    $sqlEstoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
    $stmtEstoque = $pdo->prepare($sqlEstoque);

    foreach ($simulacoes as $s) {
        $cliente = $clientes[$s['cliente_index']] ?? null;
        if (!$cliente) continue;

        $valor_total = 0;
        $itens_a_inserir = [];

        // Calcular subtotal de cada item
        foreach ($s['itens'] as $prodIdx => $qtd) {
            $prod = $produtos[$prodIdx] ?? null;
            if (!$prod) continue;

            $preco_unitario = floatval($prod['preco']);
            $subtotal = $qtd * $preco_unitario;
            $valor_total += $subtotal;

            $itens_a_inserir[] = [
                'produto_id' => $prod['id'],
                'quantidade' => $qtd,
                'preco_unitario' => $preco_unitario,
                'subtotal' => $subtotal
            ];
        }

        // Se o status for entregue ou aguardando/saiu para entrega e já foi pesado, a quantidade real e preco real são preenchidos
        $jaPesado = in_array($s['status_entrega'], ['Aguardando Entrega', 'Saiu para entrega', 'Entregue']);
        
        $data_pedido = date('Y-m-d H:i:s', strtotime('-' . rand(1, 4) . ' days'));
        $entregue_em = ($s['status_entrega'] === 'Entregue') ? date('Y-m-d H:i:s', strtotime('-1 days')) : null;

        $stmtPedido->execute([
            $cliente['id'],
            $valor_total,
            $s['status_pagamento'],
            $s['status_entrega'],
            $s['tipo_pedido'],
            $s['obs'],
            $data_pedido,
            $entregue_em
        ]);
        
        $pedidoId = $pdo->lastInsertId();

        foreach ($itens_a_inserir as $item) {
            $qtd_real = $jaPesado ? $item['quantidade'] : null;
            $preco_real = $jaPesado ? $item['subtotal'] : null;

            $stmtItem->execute([
                $pedidoId,
                $item['produto_id'],
                $item['quantidade'],
                $qtd_real,
                $item['preco_unitario'],
                $preco_real
            ]);

            // Decrementar estoque
            $stmtEstoque->execute([$item['quantidade'], $item['produto_id']]);
        }

        echo "<p>✅ Pedido #$pedidoId criado para <b>{$cliente['nome']}</b> ({$s['status_entrega']}, R$ " . number_format($valor_total, 2, ',', '.') . ")</p>";
    }

    $pdo->commit();
    echo "<h2 style='color: green;'>🎉 Pedidos simulados com sucesso! Banco pronto para testes nas abas de separação, logística e entregas.</h2>";

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo "<h2 style='color: red;'>❌ Erro ao simular pedidos:</h2><p>" . $e->getMessage() . "</p>";
}
?>
