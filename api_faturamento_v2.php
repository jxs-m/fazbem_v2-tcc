<?php
// Caminho: faz_bem_v2/api_faturamento_v2.php
session_start();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';

// Verificação simples de login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$acao = $_GET['acao'] ?? '';
$pdo = Database::getConexao();

try {
    switch ($acao) {
        case 'gerar_faturas':
            if ($_SESSION['tipo_usuario'] !== 'admin') {
                throw new Exception("Apenas admins podem gerar faturas.");
            }

            $mes_referencia = date('Y-m'); // Ex: 2026-05

            // Buscar todos os clientes com assinaturas Ativas ou Pausadas
            $sql = "SELECT a.usuario_id, a.valor_mensal, u.saldo_compensacao 
                    FROM assinaturas a 
                    JOIN usuarios u ON a.usuario_id = u.id 
                    WHERE a.status IN ('Ativa', 'Pausada')";
            $stmt = $pdo->query($sql);
            $assinantes = $stmt->fetchAll();

            $faturas_geradas = 0;
            foreach ($assinantes as $ass) {
                $u_id = $ass['usuario_id'];
                
                // Verifica se já gerou fatura para este mes
                $check = $pdo->prepare("SELECT id FROM faturas_mensais WHERE usuario_id = ? AND mes_referencia = ?");
                $check->execute([$u_id, $mes_referencia]);
                if ($check->rowCount() > 0) continue;

                $valor_mensalidade = floatval($ass['valor_mensal']);
                $saldo = floatval($ass['saldo_compensacao']);

                // Buscar total de extras pendentes e que já passaram pela balança (Aguardando Entrega, Saiu para entrega, Entregue)
                $sqlExtras = "SELECT IFNULL(SUM(valor_total), 0) as total_extras 
                              FROM pedidos 
                              WHERE usuario_id = ? AND tipo_pedido IN ('Extra', 'Avulso') AND status_pagamento = 'Pendente' AND status_entrega != 'Em separação'";
                $stmtExt = $pdo->prepare($sqlExtras);
                $stmtExt->execute([$u_id]);
                $valor_extras = floatval($stmtExt->fetchColumn());

                $subtotal = $valor_mensalidade + $valor_extras;
                $desconto = min($saldo, $subtotal); // Não desconta mais do que a fatura
                $total = $subtotal - $desconto;

                $pdo->beginTransaction();
                
                // Insere Fatura
                $sqlInsert = "INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([$u_id, $mes_referencia, $valor_mensalidade, $valor_extras, $desconto, $total]);
                
                // Abate o saldo do usuario
                if ($desconto > 0) {
                    $sqlUpdateSaldo = "UPDATE usuarios SET saldo_compensacao = saldo_compensacao - ? WHERE id = ?";
                    $pdo->prepare($sqlUpdateSaldo)->execute([$desconto, $u_id]);
                    
                    // Registra debito na carteira
                    $pdo->prepare("INSERT INTO transacoes_financeiras (usuario_id, tipo, valor, motivo) VALUES (?, 'Debito', ?, ?)")
                        ->execute([$u_id, $desconto, "Abatimento na Fatura de $mes_referencia"]);
                }

                // Marca pedidos como 'Faturado' (ou 'Pago' para não entrarem no próximo mês)
                $pdo->prepare("UPDATE pedidos SET status_pagamento = 'Pago', obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Faturado em ', ?, ']') WHERE usuario_id = ? AND tipo_pedido IN ('Extra', 'Avulso') AND status_pagamento = 'Pendente'")
                    ->execute([$mes_referencia, $u_id]);

                $pdo->commit();
                $faturas_geradas++;
            }

            echo json_encode(['success' => true, 'message' => "Foram geradas $faturas_geradas faturas com sucesso."]);
            break;

        case 'minhas_faturas':
            if ($_SESSION['tipo_usuario'] !== 'cliente') throw new Exception("Apenas clientes.");
            $u_id = $_SESSION['usuario_id'];
            $stmt = $pdo->prepare("SELECT * FROM faturas_mensais WHERE usuario_id = ? ORDER BY id DESC");
            $stmt->execute([$u_id]);
            echo json_encode(['success' => true, 'faturas' => $stmt->fetchAll()]);
            break;

        case 'listar_faturas_admin':
            if ($_SESSION['tipo_usuario'] !== 'admin') throw new Exception("Apenas admins.");
            $sql = "SELECT f.*, u.nome as cliente 
                    FROM faturas_mensais f 
                    JOIN usuarios u ON f.usuario_id = u.id 
                    ORDER BY f.id DESC";
            $stmt = $pdo->query($sql);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'pagar_fatura':
            if ($_SESSION['tipo_usuario'] !== 'cliente') throw new Exception("Apenas clientes.");
            $data = json_decode(file_get_contents('php://input'), true);
            $f_id = $data['fatura_id'] ?? null;
            if (!$f_id) throw new Exception("ID da fatura inválido.");

            $pdo->prepare("UPDATE faturas_mensais SET status = 'Pago', pago_em = NOW() WHERE id = ? AND usuario_id = ?")
                ->execute([$f_id, $_SESSION['usuario_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Fatura paga com sucesso!']);
            break;

        default:
            throw new Exception("Ação desconhecida.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
