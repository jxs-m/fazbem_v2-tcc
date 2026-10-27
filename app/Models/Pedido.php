<?php
// Caminho: app/Models/Pedido.php

require_once __DIR__ . '/../Database.php';

class Pedido {
    private $pdo;

    public const TRANSICOES_ENTREGA = [
        'Em separação' => ['Aguardando Entrega'],
        'Aguardando Entrega' => ['Saiu para entrega'],
        'Saiu para entrega' => ['Entregue'],
        'Entregue' => [],
    ];

    public const PAPEIS_TRANSICAO_ENTREGA = [
        'Em separação->Aguardando Entrega' => ['separador', 'admin'],
        'Aguardando Entrega->Saiu para entrega' => ['entregador', 'admin'],
        'Saiu para entrega->Entregue' => ['entregador', 'admin'],
    ];

    public const TRANSICOES_PAGAMENTO = [
        'Pendente' => ['Pago', 'Cancelado'],
        'Pago' => ['Cancelado'],
        'Cancelado' => [],
    ];

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

   
    public function listarTodos() {
     
        $sql = "SELECT p.id, p.valor_total, p.status_pagamento, p.status_entrega, p.data_pedido, u.nome as cliente
                FROM pedidos p
                JOIN usuarios u ON p.usuario_id = u.id
                ORDER BY p.data_pedido DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

   
    public function buscarDetalhes($id) {
        $sql = "SELECT p.*, u.nome, u.telefone, u.endereco, u.ponto_referencia 
                FROM pedidos p 
                JOIN usuarios u ON p.usuario_id = u.id 
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

   
    public function buscarItens($pedido_id) {
        $sql = "SELECT i.quantidade, i.quantidade_real, i.preco_unitario, i.preco_real, pr.id as produto_id, pr.nome, pr.unidade, pr.tipo_venda, pr.peso_estimado_g, pr.preco 
                FROM itens_pedido i
                JOIN produtos pr ON i.produto_id = pr.id
                WHERE i.pedido_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pedido_id]);
        return $stmt->fetchAll();
    }

    public function buscarPedidoSemana($usuario_id) {
        $sql = "SELECT id, valor_total, status_pagamento, status_entrega, obs_pontual, data_pedido, tipo_pedido 
                FROM pedidos 
                WHERE usuario_id = ? AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0)
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

   
    /**
     * Registra auditoria de transição de estado de pedido.
     */
    public function registrarAuditoriaStatus($pedido_id, $usuario_id, $papel_usuario, $campo, $estado_anterior, $estado_novo, $motivo = null) {
        $sql = "INSERT INTO historico_status_pedidos (pedido_id, usuario_id, papel_usuario, campo, estado_anterior, estado_novo, motivo)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            (int) $pedido_id,
            $usuario_id !== null ? (int) $usuario_id : null,
            $papel_usuario !== null ? substr((string) $papel_usuario, 0, 30) : null,
            $campo,
            (string) $estado_anterior,
            (string) $estado_novo,
            $motivo !== null ? substr(trim((string) $motivo), 0, 255) : null
        ]);
    }

    /**
     * Verifica se um pedido pertence à rota ativa do entregador na data atual.
     */
    public function validarEscopoEntregador(array $pedido, int $entregador_id): void {
        $rotaId = isset($pedido['rota_id']) ? (int) $pedido['rota_id'] : 0;
        if ($rotaId <= 0) {
            throw new Exception("Acesso negado: pedido não está vinculado a uma rota atribuída.", 403);
        }

        $stmtRota = $this->pdo->prepare(
            "SELECT id, entregador_id, data_rota, status, CURDATE() as hoje 
             FROM rotas 
             WHERE id = ? 
             FOR UPDATE"
        );
        $stmtRota->execute([$rotaId]);
        $rota = $stmtRota->fetch();

        if (!$rota) {
            throw new Exception("Acesso negado: rota associada ao pedido não foi encontrada.", 403);
        }
        if ((int) ($rota['entregador_id'] ?? 0) !== $entregador_id) {
            throw new Exception("Acesso negado: pedido pertence à rota de outro entregador.", 403);
        }
        if ($rota['data_rota'] !== $rota['hoje']) {
            throw new Exception("Acesso negado: a rota deste pedido não corresponde à data operacional de hoje.", 403);
        }
        if (!in_array($rota['status'], ['Pendente', 'Rota Iniciada'], true)) {
            throw new Exception("Acesso negado: a rota deste pedido já foi concluída ou não está ativa.", 403);
        }
        if (!empty($pedido['data_entrega']) && $pedido['data_entrega'] !== $rota['hoje']) {
            throw new Exception("Acesso negado: a data de entrega do pedido não corresponde à data de hoje.", 403);
        }
    }

    /**
     * Verifica se um pedido está no escopo operacional permitido para separação.
     */
    public function validarEscopoSeparador(array $pedido): void {
        if (($pedido['status_pagamento'] ?? '') === 'Cancelado') {
            throw new Exception("Acesso negado: pedido cancelado não pode ser separado.", 403);
        }
        if (($pedido['status_entrega'] ?? '') !== 'Em separação') {
            throw new Exception("Transição inválida: pedido não está no estado 'Em separação'.", 400);
        }
        if (empty($pedido['NoEscopoOperacional'])) {
            throw new Exception("Acesso negado: pedido fora do ciclo operacional de separação.", 403);
        }
    }

    /**
     * Centraliza e valida transições de status (entrega e pagamento), escopo por papel e auditoria.
     */
    public function atualizarStatus($id, $tipo, $valor, $ator_id = null, $papel_ator = null, $motivo = null) {
        $colunasPermitidas = ['pagamento' => 'status_pagamento', 'entrega' => 'status_entrega'];
        if (!isset($colunasPermitidas[$tipo])) {
            throw new Exception("Tipo de atualização inválido.", 400);
        }
        $coluna = $colunasPermitidas[$tipo];

        $atorId = $ator_id !== null ? (int) $ator_id : (isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null);
        $papel = $papel_ator !== null ? (string) $papel_ator : (string) ($_SESSION['tipo_usuario'] ?? '');

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtPed = $this->pdo->prepare(
                "SELECT id, usuario_id, status_pagamento, status_entrega, rota_id, data_entrega, data_pedido,
                        (
                            (data_entrega IS NOT NULL AND data_entrega >= CURDATE())
                            OR (data_entrega IS NULL AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0))
                        ) AS NoEscopoOperacional
                 FROM pedidos 
                 WHERE id = ? 
                 FOR UPDATE"
            );
            $stmtPed->execute([(int) $id]);
            $pedido = $stmtPed->fetch();

            if (!$pedido) {
                throw new Exception("Pedido não encontrado.", 404);
            }

            if ($tipo === 'entrega') {
                $estadoAtual = (string) $pedido['status_entrega'];
                $novoEstado = (string) $valor;

                if (!array_key_exists($novoEstado, self::TRANSICOES_ENTREGA)) {
                    throw new Exception("Status de entrega inválido.", 400);
                }

                if ($pedido['status_pagamento'] === 'Cancelado') {
                    throw new Exception("Não é permitido alterar o status de entrega de um pedido cancelado.", 400);
                }

                if ($estadoAtual === 'Entregue') {
                    throw new Exception("Transição inválida: pedido já finalizado ('Entregue') não pode ser alterado.", 400);
                }

                $permitidos = self::TRANSICOES_ENTREGA[$estadoAtual] ?? [];
                if (!in_array($novoEstado, $permitidos, true)) {
                    throw new Exception("Transição de status de entrega não permitida: '{$estadoAtual}' -> '{$novoEstado}'.", 400);
                }

                $chaveTransicao = "{$estadoAtual}->{$novoEstado}";
                $papeisAutorizados = self::PAPEIS_TRANSICAO_ENTREGA[$chaveTransicao] ?? [];
                if (!in_array($papel, $papeisAutorizados, true)) {
                    throw new Exception("Acesso negado: o papel '{$papel}' não tem permissão para a transição '{$estadoAtual}' -> '{$novoEstado}'.", 403);
                }

                if ($papel === 'entregador') {
                    if (!$atorId) {
                        throw new Exception("Acesso negado: entregador não identificado.", 403);
                    }
                    $this->validarEscopoEntregador($pedido, $atorId);
                } elseif ($papel === 'separador') {
                    $this->validarEscopoSeparador($pedido);
                }

                if ($novoEstado === 'Entregue') {
                    $sql = "UPDATE pedidos SET status_entrega = ?, entregue_em = NOW() WHERE id = ?";
                } else {
                    $sql = "UPDATE pedidos SET status_entrega = ? WHERE id = ?";
                }
                $stmtUpd = $this->pdo->prepare($sql);
                $stmtUpd->execute([$novoEstado, (int) $id]);

                // Atualiza ciclo de vida da rota associada, quando houver
                if (!empty($pedido['rota_id'])) {
                    $rotaId = (int) $pedido['rota_id'];
                    if ($novoEstado === 'Saiu para entrega') {
                        $this->pdo->prepare("UPDATE rotas SET status = 'Rota Iniciada' WHERE id = ? AND status = 'Pendente'")
                            ->execute([$rotaId]);
                    } elseif ($novoEstado === 'Entregue') {
                        $stmtRestantes = $this->pdo->prepare(
                            "SELECT COUNT(id) FROM pedidos WHERE rota_id = ? AND status_pagamento != 'Cancelado' AND status_entrega != 'Entregue'"
                        );
                        $stmtRestantes->execute([$rotaId]);
                        if ((int) $stmtRestantes->fetchColumn() === 0) {
                            $this->pdo->prepare("UPDATE rotas SET status = 'Concluída' WHERE id = ?")
                                ->execute([$rotaId]);
                        }
                    }
                }

                $motivoFinal = $motivo ?? "Transição operacional de entrega ({$papel})";
                $this->registrarAuditoriaStatus($id, $atorId, $papel, 'status_entrega', $estadoAtual, $novoEstado, $motivoFinal);
            } else {
                // status_pagamento
                if (!in_array($papel, ['admin', 'sistema'], true)) {
                    throw new Exception("Acesso negado: apenas administradores ou o sistema podem alterar o status de pagamento.", 403);
                }

                $estadoAtual = (string) $pedido['status_pagamento'];
                $novoEstado = (string) $valor;

                if (!array_key_exists($novoEstado, self::TRANSICOES_PAGAMENTO)) {
                    throw new Exception("Status de pagamento inválido.", 400);
                }

                if ($estadoAtual === 'Cancelado') {
                    throw new Exception("Transição inválida: pedido com pagamento 'Cancelado' está finalizado e não pode retornar a outro estado.", 400);
                }

                $permitidos = self::TRANSICOES_PAGAMENTO[$estadoAtual] ?? [];
                if (!in_array($novoEstado, $permitidos, true)) {
                    throw new Exception("Transição de status de pagamento não permitida: '{$estadoAtual}' -> '{$novoEstado}'.", 400);
                }

                if ($estadoAtual === 'Pago' && $novoEstado === 'Cancelado' && $pedido['status_entrega'] === 'Entregue' && $papel !== 'sistema') {
                    throw new Exception("Não é permitido cancelar o pagamento de um pedido que já foi entregue.", 400);
                }

                $stmtUpd = $this->pdo->prepare("UPDATE pedidos SET status_pagamento = ? WHERE id = ?");
                $stmtUpd->execute([$novoEstado, (int) $id]);

                $motivoFinal = $motivo ?? "Alteração de status de pagamento ({$papel})";
                $this->registrarAuditoriaStatus($id, $atorId, $papel, 'status_pagamento', $estadoAtual, $novoEstado, $motivoFinal);
            }

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function buscarPorUsuario($usuario_id) {
        $sql = "SELECT id, valor_total, status_pagamento, status_entrega, data_pedido 
                FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    }

    /**
     * SEC-03: Busca o último pedido de semanas anteriores do usuário.
     */
    public function buscarUltimoPedidoAnterior($usuario_id) {
        $sql = "SELECT id FROM pedidos 
                WHERE usuario_id = ? 
                AND YEARWEEK(data_pedido, 0) < YEARWEEK(NOW(), 0)
                ORDER BY data_pedido DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

    public function verificarPedidoExistenteSemana($usuario_id) {
        $sql = "SELECT COUNT(id) as total FROM pedidos 
                WHERE usuario_id = ? AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0) AND status_pagamento != 'Cancelado'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch()['total'] > 0;
    }

    /**
     * Busca pedido ativo da semana do usuário (excluindo cancelados) ou pela chave de idempotência.
     */
    public function buscarPedidoAtivoSemanaOuChave($usuario_id, $idempotency_key = null) {
        if (!empty($idempotency_key)) {
            $stmtKey = $this->pdo->prepare(
                "SELECT id, valor_total, status_pagamento, status_entrega, tipo_pedido, forma_pagamento, transacao_id, idempotency_key, mp_status 
                 FROM pedidos WHERE usuario_id = ? AND idempotency_key = ? LIMIT 1"
            );
            $stmtKey->execute([$usuario_id, $idempotency_key]);
            $foundByKey = $stmtKey->fetch();
            if ($foundByKey) {
                return $foundByKey;
            }
        }

        $sql = "SELECT id, valor_total, status_pagamento, status_entrega, tipo_pedido, forma_pagamento, transacao_id, idempotency_key, mp_status 
                FROM pedidos 
                WHERE usuario_id = ? AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0) AND status_pagamento != 'Cancelado'
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return $stmt->fetch();
    }

    public function processarCarrinho($carrinho) {
        $total_calculado = 0;
        $itens_processados = [];

        $stmtProd = $this->pdo->prepare("SELECT nome, preco, unidade, peso_estimado_g, tipo_venda FROM produtos WHERE id = ?");
        
        foreach ($carrinho as $item) {
            $produto_id = intval($item['id'] ?? 0);
            $stmtProd->execute([$produto_id]);
            $prodInfo = $stmtProd->fetch();
            
            if (!$prodInfo) {
                throw new Exception("Produto não encontrado.");
            }
            
            $db_preco_base = floatval($prodInfo['preco']);
            $peso_estimado_g = floatval($prodInfo['peso_estimado_g'] ?? 0);
            $tipo_venda = $prodInfo['tipo_venda'];
            $unidade = strtolower($prodInfo['unidade']);
            
            // Determina baseGrams
            $baseGrams = null;
            if (strpos($unidade, 'kg') !== false) {
                $baseGrams = 1000;
            } elseif (strpos($unidade, 'g') !== false) {
                $num = intval($unidade);
                if ($num > 0) $baseGrams = $num;
            }

            $isNovoModelo = isset($item['preco_estimado_calculado']) || isset($item['tipo_compra']);
            if ($isNovoModelo) {
                $input_qtd = floatval($item['input_qtd'] ?? 0);
                if ($input_qtd <= 0) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }
                $tipo_compra = $item['tipo_compra'] ?? 'Unidade';
                
                if ($tipo_compra === 'Unidade') {
                    $requestedGrams = $input_qtd * $peso_estimado_g;
                    $quantidade_calculada = ($baseGrams !== null) ? ($requestedGrams / $baseGrams) : $input_qtd;
                } else {
                    $requestedGrams = $input_qtd;
                    $quantidade_calculada = ($baseGrams !== null) ? ($requestedGrams / $baseGrams) : $input_qtd;
                }
            } else {
                $quantidade_calculada = floatval($item['quantidade'] ?? 0);
                if ($quantidade_calculada <= 0) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }
                $requestedGrams = $quantidade_calculada * $peso_estimado_g;
            }

            $subtotal = $quantidade_calculada * $db_preco_base;
            $total_calculado += $subtotal;

            $estoqueDecremento = $quantidade_calculada;
            if ($tipo_venda === 'Fracionado') {
                $estoqueDecremento = $requestedGrams;
            }

            $itens_processados[] = [
                'produto_id' => $produto_id,
                'quantidade' => $quantidade_calculada,
                'preco_unitario' => $db_preco_base,
                'estoque_decremento' => $estoqueDecremento
            ];
        }

        return [
            'total_calculado' => $total_calculado,
            'itens_processados' => $itens_processados
        ];
    }

    public function criarPedido($usuario_id, $total_calculado, $forma_pagamento, $itens_processados, $transacao_id = null, $status_pagamento = 'Pendente', $idempotency_key = null, $mp_status = null, $tipo_pedido = 'Extra') {
        $iniciadoAqui = !$this->pdo->inTransaction();
        try {
            if ($iniciadoAqui) {
                $this->pdo->beginTransaction();
            }

            $tiposPermitidos = ['Assinatura', 'Avulso', 'Extra', 'Reposicao'];
            $tipo = in_array($tipo_pedido, $tiposPermitidos, true) ? $tipo_pedido : 'Extra';

            // Inserir o pedido com o valor total recalculado e chave de idempotência persistida
            $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, forma_pagamento, transacao_id, idempotency_key, mp_status) 
                          VALUES (?, ?, ?, 'Em separação', ?, ?, ?, ?, ?)";
            $stmtPedido = $this->pdo->prepare($sqlPedido);
            $stmtPedido->execute([
                $usuario_id,
                $total_calculado,
                $status_pagamento,
                $tipo,
                $forma_pagamento,
                $transacao_id,
                $idempotency_key,
                $mp_status
            ]);
            
            $pedido_id = (int) $this->pdo->lastInsertId();

            // Inserir itens do pedido
            $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) 
                        VALUES (?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);

            foreach ($itens_processados as $ip) {
                $stmtItem->execute([$pedido_id, $ip['produto_id'], $ip['quantidade'], $ip['preco_unitario']]);
            }

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }

            return $pedido_id;

        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra a pesagem de separação, valida escopo/itens e transiciona 'Em separação' -> 'Aguardando Entrega' com auditoria.
     */
    public function registrarPesagemSeparacao($pedido_id, array $itensPesados, $ator_id = null, $papel_ator = null, $motivo = null) {
        $pedidoId = (int) $pedido_id;
        if ($pedidoId <= 0 || empty($itensPesados)) {
            throw new Exception("Dados inválidos para pesagem.", 400);
        }

        $atorId = $ator_id !== null ? (int) $ator_id : (isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null);
        $papel = $papel_ator !== null ? (string) $papel_ator : (string) ($_SESSION['tipo_usuario'] ?? '');

        $papeisAutorizados = self::PAPEIS_TRANSICAO_ENTREGA['Em separação->Aguardando Entrega'] ?? [];
        if (!in_array($papel, $papeisAutorizados, true)) {
            throw new Exception("Acesso negado: papel '{$papel}' não autorizado para separação.", 403);
        }

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtPed = $this->pdo->prepare(
                "SELECT id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, fatura_id,
                        (
                            (data_entrega IS NOT NULL AND data_entrega >= CURDATE())
                            OR (data_entrega IS NULL AND YEARWEEK(data_pedido, 0) = YEARWEEK(NOW(), 0))
                        ) AS NoEscopoOperacional
                 FROM pedidos 
                 WHERE id = ? 
                 FOR UPDATE"
            );
            $stmtPed->execute([$pedidoId]);
            $pedido = $stmtPed->fetch();

            if (!$pedido) {
                throw new Exception("Pedido não encontrado.", 404);
            }

            if ($pedido['status_pagamento'] === 'Cancelado') {
                throw new Exception("Acesso negado: não é permitido separar um pedido cancelado.", 403);
            }

            if ($pedido['status_entrega'] !== 'Em separação') {
                throw new Exception("Transição inválida: pedido não está em separação (estado atual: '{$pedido['status_entrega']}').", 400);
            }

            if (!empty($pedido['fatura_id'])) {
                throw new Exception("Operação bloqueada: pedido já faturado não pode ser repesado.", 400);
            }

            if ($papel === 'separador') {
                $this->validarEscopoSeparador($pedido);
            }

            $stmtItens = $this->pdo->prepare("SELECT id, preco_unitario FROM itens_pedido WHERE pedido_id = ? FOR UPDATE");
            $stmtItens->execute([$pedidoId]);
            $itensBanco = $stmtItens->fetchAll();

            if (empty($itensBanco)) {
                throw new Exception("O pedido não possui itens válidos para separação.", 400);
            }

            $mapaItens = [];
            foreach ($itensBanco as $ib) {
                $mapaItens[(int) $ib['id']] = floatval($ib['preco_unitario']);
            }

            $itensProcessadosIds = [];
            $novoTotalPedido = 0.0;
            $stmtUpdateItem = $this->pdo->prepare("UPDATE itens_pedido SET quantidade_real = ?, preco_real = ? WHERE id = ? AND pedido_id = ?");

            foreach ($itensPesados as $item) {
                $itemId = filter_var($item['item_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$itemId || !array_key_exists($itemId, $mapaItens)) {
                    throw new Exception("Item inválido ou não pertencente ao pedido informado.", 400);
                }
                if (isset($itensProcessadosIds[$itemId])) {
                    throw new Exception("Item duplicado enviado na pesagem.", 400);
                }
                if (!isset($item['quantidade_real']) || !is_numeric($item['quantidade_real'])) {
                    throw new Exception("Quantidade real inválida para o item #{$itemId}.", 400);
                }

                $qReal = round(floatval($item['quantidade_real']), 3);
                if ($qReal < 0) {
                    throw new Exception("Quantidade real não pode ser negativa.", 400);
                }

                $precoUnitario = $mapaItens[$itemId];
                $precoReal = round($qReal * $precoUnitario, 2);
                $novoTotalPedido += $precoReal;

                $stmtUpdateItem->execute([$qReal, $precoReal, $itemId, $pedidoId]);
                $itensProcessadosIds[$itemId] = true;
            }

            if (count($itensProcessadosIds) !== count($mapaItens)) {
                throw new Exception("Todos os itens do pedido devem ser pesados antes de finalizar a separação.", 400);
            }

            // Pedidos de Assinatura mantêm o valor fixo do pacote se os itens têm preço unitário 0
            $valorFinalPedido = ($pedido['tipo_pedido'] === 'Assinatura' && $novoTotalPedido <= 0.0)
                ? round(floatval($pedido['valor_total']), 2)
                : round($novoTotalPedido, 2);

            $stmtUpdPed = $this->pdo->prepare(
                "UPDATE pedidos 
                 SET valor_total = ?, 
                     status_entrega = 'Aguardando Entrega', 
                     obs_pontual = CONCAT(IFNULL(obs_pontual,''), ' [Pesado]') 
                 WHERE id = ? AND status_entrega = 'Em separação'"
            );
            $stmtUpdPed->execute([$valorFinalPedido, $pedidoId]);

            if ($stmtUpdPed->rowCount() === 0) {
                throw new Exception("Pedido não está mais em separação.", 400);
            }

            $motivoFinal = $motivo ?? "Separação e pesagem concluídas ({$papel})";
            $this->registrarAuditoriaStatus($pedidoId, $atorId, $papel, 'status_entrega', 'Em separação', 'Aguardando Entrega', $motivoFinal);

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atualiza o resultado da chamada ao provedor de pagamento para um pedido existente.
     */
    public function atualizarPagamentoMercadoPago($pedido_id, $usuario_id, $status_pagamento, $mp_status, $transacao_id = null, $forma_pagamento = null, $limpar_idempotencia = false) {
        $sql = "UPDATE pedidos 
                SET status_pagamento = ?,
                    mp_status = ?,
                    transacao_id = COALESCE(?, transacao_id),
                    forma_pagamento = COALESCE(?, forma_pagamento),
                    idempotency_key = IF(? = 1, NULL, idempotency_key)
                WHERE id = ? AND usuario_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $status_pagamento,
            $mp_status,
            $transacao_id,
            $forma_pagamento,
            $limpar_idempotencia ? 1 : 0,
            $pedido_id,
            $usuario_id
        ]);
    }
}
?>