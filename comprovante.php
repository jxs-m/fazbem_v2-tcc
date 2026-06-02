<?php
// Caminho: faz_bem_v2/comprovante.php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Por favor, faça login.");
}

require_once __DIR__ . '/app/Database.php';
$pdo = Database::getConexao();

$tipo = $_GET['tipo'] ?? 'pedido';
$id = intval($_GET['id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo_usuario'];

if ($id <= 0) {
    die("ID inválido.");
}

try {
    if ($tipo === 'pedido') {
        // Busca o pedido
        $sql = "SELECT p.*, u.nome as cliente_nome, u.email as cliente_email, u.endereco as cliente_endereco 
                FROM pedidos p 
                JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $dados = $stmt->fetch();

        if (!$dados) {
            die("Pedido não encontrado.");
        }

        // Restrição de segurança: cliente só vê seus próprios pedidos
        if ($tipo_usuario !== 'admin' && $dados['usuario_id'] != $usuario_id) {
            die("Acesso negado.");
        }

        // Busca itens do pedido
        $sqlItens = "SELECT i.*, pr.nome as produto_nome, pr.unidade 
                     FROM itens_pedido i 
                     JOIN produtos pr ON i.produto_id = pr.id 
                     WHERE i.pedido_id = ?";
        $stmtItens = $pdo->prepare($sqlItens);
        $stmtItens->execute([$id]);
        $itens = $stmtItens->fetchAll();

    } else if ($tipo === 'fatura') {
        // Busca a fatura
        $sql = "SELECT f.*, u.nome as cliente_nome, u.email as cliente_email, u.endereco as cliente_endereco 
                FROM faturas_mensais f 
                JOIN usuarios u ON f.usuario_id = u.id 
                WHERE f.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $dados = $stmt->fetch();

        if (!$dados) {
            die("Fatura não encontrada.");
        }

        // Restrição de segurança
        if ($tipo_usuario !== 'admin' && $dados['usuario_id'] != $usuario_id) {
            die("Acesso negado.");
        }
    } else {
        die("Tipo de comprovante inválido.");
    }
} catch (Exception $e) {
    die("Erro ao carregar dados do comprovante: " . $e->getMessage());
}

// Formatação dos dados para exibição segura (XSS protection)
function clean($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Pagamento #<?php echo $id; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }
        .comprovante-card {
            max-width: 600px;
            background-color: #ffffff;
            margin: 20px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-top: 8px solid #166534;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #166534;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 14px;
            color: #6b7280;
        }
        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .data-label {
            color: #4b5563;
        }
        .data-value {
            font-weight: 600;
        }
        .valor-destaque {
            font-size: 20px;
            color: #166534;
            font-weight: bold;
        }
        .status-badge {
            background-color: #dcfce7;
            color: #166534;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-badge.pendente {
            background-color: #fef3c7;
            color: #d97706;
        }
        .itens-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        .itens-table th {
            text-align: left;
            padding: 8px;
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }
        .itens-table td {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
        }
        .actions {
            margin-top: 30px;
            text-align: center;
        }
        .btn-print {
            background-color: #166534;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-print:hover {
            background-color: #14532d;
        }
        .btn-back {
            background-color: #e5e7eb;
            color: #4b5563;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-back:hover {
            background-color: #d1d5db;
        }
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .comprovante-card {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border-top: none;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="comprovante-card">
    <div class="header">
        <div class="logo">Faz Bem</div>
        <div class="subtitle">Comprovante de Transação de Pagamento</div>
    </div>

    <div class="section-title">Dados da Transação</div>
    
    <div class="data-row">
        <span class="data-label">Tipo de Comprovante:</span>
        <span class="data-value"><?php echo $tipo === 'pedido' ? 'Pedido Avulso / Adicional' : 'Fatura Mensal de Assinatura'; ?></span>
    </div>
    
    <div class="data-row">
        <span class="data-label">ID Local:</span>
        <span class="data-value">#<?php echo $id; ?></span>
    </div>

    <div class="data-row">
        <span class="data-label">ID Transação Mercado Pago:</span>
        <span class="data-value"><?php echo $dados['transacao_id'] ? clean($dados['transacao_id']) : 'N/A'; ?></span>
    </div>

    <div class="data-row">
        <span class="data-label">Data de Emissão:</span>
        <span class="data-value">
            <?php 
            $dataCampo = $tipo === 'pedido' ? $dados['data_pedido'] : ($dados['pago_em'] ?? $dados['criado_em']);
            echo date('d/m/Y H:i:s', strtotime($dataCampo)); 
            ?>
        </span>
    </div>

    <div class="data-row">
        <span class="data-label">Forma de Pagamento:</span>
        <span class="data-value"><?php echo $dados['forma_pagamento'] ? clean($dados['forma_pagamento']) : 'Não Informada'; ?></span>
    </div>

    <div class="data-row">
        <span class="data-label">Status de Pagamento:</span>
        <span class="data-value">
            <span class="status-badge <?php echo strtolower($dados['status_pagamento'] ?? $dados['status'] ?? '') === 'pago' ? '' : 'pendente'; ?>">
                <?php echo clean($dados['status_pagamento'] ?? $dados['status'] ?? 'Pendente'); ?>
            </span>
        </span>
    </div>

    <div class="section-title">Dados do Cliente</div>
    <div class="data-row">
        <span class="data-label">Nome:</span>
        <span class="data-value"><?php echo clean($dados['cliente_nome']); ?></span>
    </div>
    <div class="data-row">
        <span class="data-label">Email:</span>
        <span class="data-value"><?php echo clean($dados['cliente_email']); ?></span>
    </div>
    <div class="data-row">
        <span class="data-label">Endereço:</span>
        <span class="data-value" style="max-width: 350px; text-align: right;"><?php echo clean($dados['cliente_endereco']); ?></span>
    </div>

    <?php if ($tipo === 'pedido' && !empty($itens)): ?>
        <div class="section-title">Itens do Pedido</div>
        <table class="itens-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th style="text-align: right;">Preço Unit.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): 
                    $qtd = floatval($item['quantidade_real'] ?? $item['quantidade']);
                    $preco_unit = floatval($item['preco_unitario']);
                    $subtotal = floatval($item['preco_real'] ?? ($qtd * $preco_unit));
                ?>
                    <tr>
                        <td><?php echo clean($item['produto_nome']); ?></td>
                        <td><?php echo $qtd . ' ' . clean($item['unidade']); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($preco_unit, 2, ',', '.'); ?></td>
                        <td style="text-align: right; font-weight: 600;">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($tipo === 'fatura'): ?>
        <div class="section-title">Resumo da Fatura (<?php echo clean($dados['mes_referencia']); ?>)</div>
        <div class="data-row">
            <span class="data-label">Valor Mensalidade:</span>
            <span class="data-value">R$ <?php echo number_format($dados['valor_mensalidade'], 2, ',', '.'); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Valor Adicionais/Extras:</span>
            <span class="data-value">R$ <?php echo number_format($dados['valor_extras'], 2, ',', '.'); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Desconto Aplicado (Créditos):</span>
            <span class="data-value">- R$ <?php echo number_format($dados['valor_desconto_creditos'], 2, ',', '.'); ?></span>
        </div>
    <?php endif; ?>

    <div class="data-row" style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb;">
        <span class="data-label" style="font-weight: bold; font-size: 16px;">Valor Total Pago:</span>
        <span class="data-value valor-destaque">R$ <?php echo number_format($dados['valor_total'], 2, ',', '.'); ?></span>
    </div>

    <div class="actions">
        <a href="perfil.html" class="btn-back">Voltar ao Perfil</a>
        <button onclick="window.print()" class="btn-print">Imprimir / Salvar PDF</button>
    </div>
</div>

</body>
</html>
