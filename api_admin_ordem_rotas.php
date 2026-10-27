<?php
require_once __DIR__ . '/cors.php';

// Caminho: faz_bem_v2/api_admin_ordem_rotas.php

header('Content-Type: application/json');

require_once __DIR__ . '/app/Database.php';

Security::checkCSRF();

// Verificação de Segurança (Bloqueia quem não é admin autenticado)
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Área restrita.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input) || empty($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    try {
        $pdo = Database::getConexao();
        $pdo->beginTransaction();

        // Suporta formato legado [{id, ordem}] e formato expandido {entregador_id, data_rota, pedidos: [{id, ordem}]}
        $listaPedidos = isset($input['pedidos']) && is_array($input['pedidos']) ? $input['pedidos'] : $input;
        $entregadorId = isset($input['entregador_id']) ? filter_var($input['entregador_id'], FILTER_VALIDATE_INT) : null;
        $dataRota = isset($input['data_rota']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['data_rota'])
            ? (string) $input['data_rota']
            : date('Y-m-d');

        $rotaId = null;
        if ($entregadorId) {
            // Valida se o usuário atribuído é entregador válido
            $stmtEnt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND tipo_usuario = 'entregador'");
            $stmtEnt->execute([$entregadorId]);
            if (!$stmtEnt->fetch()) {
                throw new Exception("Entregador informado é inválido.", 400);
            }

            $stmtRota = $pdo->prepare("SELECT id FROM rotas WHERE entregador_id = ? AND data_rota = ? AND status IN ('Pendente', 'Rota Iniciada') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmtRota->execute([$entregadorId, $dataRota]);
            $rotaExistente = $stmtRota->fetch();

            if ($rotaExistente) {
                $rotaId = (int) $rotaExistente['id'];
            } else {
                $stmtInsRota = $pdo->prepare("INSERT INTO rotas (entregador_id, data_rota, status) VALUES (?, ?, 'Pendente')");
                $stmtInsRota->execute([$entregadorId, $dataRota]);
                $rotaId = (int) $pdo->lastInsertId();
            }
        }

        // Apenas pedidos não finalizados ('Entregue') e não cancelados podem ter ordem/rota alteradas
        $stmtCheck = $pdo->prepare("SELECT id, status_entrega, status_pagamento FROM pedidos WHERE id = ? FOR UPDATE");
        $stmtUpdateOrdem = $pdo->prepare("UPDATE pedidos SET ordem_entrega = ?, rota_id = COALESCE(?, rota_id), data_entrega = COALESCE(?, data_entrega) WHERE id = ?");

        foreach ($listaPedidos as $item) {
            if (isset($item['id']) && isset($item['ordem'])) {
                $pedId = filter_var($item['id'], FILTER_VALIDATE_INT);
                $ordem = filter_var($item['ordem'], FILTER_VALIDATE_INT);
                if (!$pedId || $ordem === false || $ordem < 1) {
                    throw new Exception("Ordem ou ID de pedido inválido.", 400);
                }

                $stmtCheck->execute([$pedId]);
                $pedRow = $stmtCheck->fetch();
                if (!$pedRow) {
                    throw new Exception("Pedido #{$pedId} não encontrado.", 404);
                }
                if ($pedRow['status_entrega'] === 'Entregue' || $pedRow['status_pagamento'] === 'Cancelado') {
                    throw new Exception("Pedido #{$pedId} já está finalizado/cancelado e não pode ter a rota alterada.", 400);
                }

                $stmtUpdateOrdem->execute([$ordem, $rotaId, $rotaId ? $dataRota : null, $pedId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!', 'rota_id' => $rotaId]);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("DB Error na ordem de rotas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $code = (int) $e->getCode();
        if (!in_array($code, [400, 403, 404], true)) {
            $code = 400;
        }
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>
