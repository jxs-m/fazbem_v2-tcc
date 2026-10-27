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
        $sql = "SELECT i.quantidade, i.quantidade_real, i.preco_unitario, i.preco_real, pr.id as produto_id,
                       COALESCE(i.produto_nome, pr.nome) as nome,
                       COALESCE(i.produto_unidade, pr.unidade) as unidade,
                       pr.tipo_venda, pr.peso_estimado_g, i.preco_unitario as preco 
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

                if ($novoEstado === 'Cancelado') {
                    $stmtUpd = $this->pdo->prepare("UPDATE pedidos SET status_pagamento = ?, semana_ativa_chave = NULL WHERE id = ?");
                    $stmtUpd->execute([$novoEstado, (int) $id]);
                    $this->estornarEstoquePedido((int) $id, $motivo ?? "Cancelamento de pagamento ({$papel})");
                } else {
                    $stmtUpd = $this->pdo->prepare("UPDATE pedidos SET status_pagamento = ? WHERE id = ?");
                    $stmtUpd->execute([$novoEstado, (int) $id]);
                }

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
        if (!is_array($carrinho) || empty($carrinho)) {
            throw new Exception("O carrinho está vazio ou inválido.");
        }

        $total_calculado = 0.0;
        $itens_processados = [];
        $produtosVistos = [];

        $stmtProd = $this->pdo->prepare(
            "SELECT id, nome, preco, unidade, estoque_atual, peso_estimado_g, tipo_venda, temporario, duracao_dias, criado_em, ativo 
             FROM produtos WHERE id = ?"
        );

        foreach ($carrinho as $item) {
            if (!is_array($item)) {
                throw new Exception("Formato de item inválido no carrinho.");
            }

            $produto_id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$produto_id || $produto_id <= 0) {
                throw new Exception("Produto inválido no carrinho.");
            }

            if (isset($produtosVistos[$produto_id])) {
                throw new Exception("Produto duplicado no carrinho não é permitido.");
            }
            $produtosVistos[$produto_id] = true;

            $stmtProd->execute([$produto_id]);
            $prodInfo = $stmtProd->fetch();

            if (!$prodInfo) {
                throw new Exception("Produto não encontrado.");
            }

            if ((int) ($prodInfo['ativo'] ?? 1) !== 1) {
                throw new Exception("O produto " . $prodInfo['nome'] . " está inativo ou indisponível.");
            }

            if ((int) ($prodInfo['temporario'] ?? 0) === 1 && !empty($prodInfo['duracao_dias']) && (int) $prodInfo['duracao_dias'] > 0 && !empty($prodInfo['criado_em'])) {
                $criado_ts = strtotime(date('Y-m-d', strtotime($prodInfo['criado_em'])));
                $hoje_ts = strtotime(date('Y-m-d'));
                $dias_passados = ($hoje_ts - $criado_ts) / 86400;
                if (((int) $prodInfo['duracao_dias'] - $dias_passados) <= 0) {
                    throw new Exception("O produto temporário " . $prodInfo['nome'] . " está expirado.");
                }
            }

            $db_preco_base = floatval($prodInfo['preco']);
            if ($db_preco_base < 0) {
                throw new Exception("Preço base inválido para o produto " . $prodInfo['nome']);
            }
            $peso_estimado_g = floatval($prodInfo['peso_estimado_g'] ?? 0);
            $tipo_venda = $prodInfo['tipo_venda'];
            $unidade = strtolower(trim((string) $prodInfo['unidade']));

            // Determina baseGrams conforme a unidade cadastrada no banco
            $baseGrams = null;
            if (strpos($unidade, 'kg') !== false) {
                $baseGrams = 1000.0;
            } elseif (strpos($unidade, 'g') !== false) {
                $num = intval($unidade);
                $baseGrams = ($num > 0) ? (float) $num : 1.0;
            }

            $isNovoModelo = isset($item['preco_estimado_calculado']) || isset($item['tipo_compra']) || isset($item['input_qtd']);
            if ($isNovoModelo) {
                $rawQtd = $item['input_qtd'] ?? null;
                if ($rawQtd === null || $rawQtd === '' || !is_numeric($rawQtd)) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }
                $rawStr = str_replace(',', '.', trim((string) $rawQtd));
                if (!preg_match('/^\d+(\.\d{1,3})?$/', $rawStr)) {
                    throw new Exception("Precisão decimal inválida para o produto " . $prodInfo['nome']);
                }

                $input_qtd = floatval($rawQtd);
                if ($input_qtd <= 0) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }

                $tipo_compra = $item['tipo_compra'] ?? 'Unidade';
                if (!in_array($tipo_compra, ['Unidade', 'Gramas'], true)) {
                    throw new Exception("Unidade de compra inválida para o produto " . $prodInfo['nome']);
                }

                if ($tipo_compra === 'Gramas') {
                    if ($tipo_venda === 'Inteiro') {
                        throw new Exception("Compra fracionada em gramas não é permitida para o produto inteiro " . $prodInfo['nome']);
                    }
                    if ($input_qtd < 50 || $input_qtd > 20000) {
                        throw new Exception("Quantidade em gramas fora dos limites permitidos (50g a 20000g) para " . $prodInfo['nome']);
                    }
                    if ($baseGrams === null && $peso_estimado_g <= 0) {
                        throw new Exception("Configuração de peso inválida para compra em gramas de " . $prodInfo['nome']);
                    }
                    $requestedGrams = $input_qtd;
                    $quantidade_calculada = ($baseGrams !== null)
                        ? ($requestedGrams / $baseGrams)
                        : ($requestedGrams / $peso_estimado_g);
                } else {
                    // Compra por Unidade
                    if (abs($input_qtd - round($input_qtd)) > 0.0001) {
                        throw new Exception("A quantidade em unidades deve ser um número inteiro para " . $prodInfo['nome']);
                    }
                    if ($input_qtd < 1 || $input_qtd > 50) {
                        throw new Exception("Quantidade fora dos limites permitidos (1 a 50 unidades) para " . $prodInfo['nome']);
                    }
                    if ($baseGrams !== null && $peso_estimado_g <= 0) {
                        throw new Exception("Peso estimado não configurado para conversão por unidade de " . $prodInfo['nome']);
                    }
                    $requestedGrams = $input_qtd * $peso_estimado_g;
                    $quantidade_calculada = ($baseGrams !== null) ? ($requestedGrams / $baseGrams) : $input_qtd;
                }
            } else {
                $rawQtd = $item['quantidade'] ?? null;
                if ($rawQtd === null || $rawQtd === '' || !is_numeric($rawQtd)) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }
                $rawStr = str_replace(',', '.', trim((string) $rawQtd));
                if (!preg_match('/^\d+(\.\d{1,3})?$/', $rawStr)) {
                    throw new Exception("Precisão decimal inválida para o produto " . $prodInfo['nome']);
                }

                $quantidade_calculada = floatval($rawQtd);
                if ($quantidade_calculada <= 0) {
                    throw new Exception("Quantidade inválida para o produto " . $prodInfo['nome']);
                }

                if ($tipo_venda === 'Inteiro' && $baseGrams === null) {
                    if (abs($quantidade_calculada - round($quantidade_calculada)) > 0.0001) {
                        throw new Exception("Produto vendido por unidade inteira não aceita fração: " . $prodInfo['nome']);
                    }
                    if ($quantidade_calculada < 1 || $quantidade_calculada > 50) {
                        throw new Exception("Quantidade fora dos limites permitidos (1 a 50 unidades) para " . $prodInfo['nome']);
                    }
                } else {
                    $minQtd = ($baseGrams === 1000.0) ? 0.050 : 1.0;
                    $maxQtd = ($baseGrams === 1000.0) ? 50.000 : 20000.0;
                    if ($quantidade_calculada < $minQtd || $quantidade_calculada > $maxQtd) {
                        throw new Exception("Quantidade fora dos limites permitidos para " . $prodInfo['nome']);
                    }
                }
            }

            $quantidade_calculada = round($quantidade_calculada, 3);
            if ($quantidade_calculada <= 0) {
                throw new Exception("Quantidade calculada inválida para o produto " . $prodInfo['nome']);
            }

            // O decremento de estoque deve estar sempre na mesma unidade de medida da coluna estoque_atual do produto
            $estoqueDecremento = $quantidade_calculada;
            $estoqueAtual = round(floatval($prodInfo['estoque_atual'] ?? 0), 3);

            if ($estoqueAtual + 0.0001 < $estoqueDecremento) {
                throw new Exception("Estoque insuficiente para o produto " . $prodInfo['nome'] . " (disponível: {$estoqueAtual} {$prodInfo['unidade']}).");
            }

            $subtotal = round($quantidade_calculada * $db_preco_base, 2);
            $total_calculado = round($total_calculado + $subtotal, 2);

            $itens_processados[] = [
                'produto_id' => $produto_id,
                'produto_nome' => $prodInfo['nome'],
                'produto_unidade' => $prodInfo['unidade'],
                'quantidade' => $quantidade_calculada,
                'preco_unitario' => $db_preco_base,
                'estoque_decremento' => $estoqueDecremento
            ];
        }

        return [
            'total_calculado' => round($total_calculado, 2),
            'itens_processados' => $itens_processados
        ];
    }

    /**
     * Reserva estoque de forma condicional (estoque_atual >= ?) dentro da transação ativa e registra em movimentacoes_estoque.
     */
    public function reservarEstoqueItens(int $pedido_id, array $itens, string $origem = 'Pedido'): void {
        $stmtReserva = $this->pdo->prepare(
            "UPDATE produtos 
             SET estoque_atual = estoque_atual - ? 
             WHERE id = ? AND ativo = 1 AND estoque_atual >= ?"
        );
        $stmtMov = $this->pdo->prepare(
            "INSERT INTO movimentacoes_estoque (produto_id, pedido_id, tipo, quantidade, descricao) 
             VALUES (?, ?, 'Reserva', ?, ?)"
        );
        $stmtNome = $this->pdo->prepare("SELECT nome, unidade, estoque_atual, ativo FROM produtos WHERE id = ?");

        foreach ($itens as $item) {
            $prodId = (int) ($item['produto_id'] ?? $item['id'] ?? 0);
            $qtd = round(floatval($item['estoque_decremento'] ?? $item['quantidade'] ?? 0), 3);
            if ($prodId <= 0 || $qtd <= 0) {
                throw new Exception("Item inválido para reserva de estoque.");
            }

            $stmtReserva->execute([$qtd, $prodId, $qtd]);
            if ($stmtReserva->rowCount() === 0) {
                $stmtNome->execute([$prodId]);
                $p = $stmtNome->fetch();
                $nomeProd = $p['nome'] ?? ("#" . $prodId);
                throw new Exception("Estoque insuficiente ou produto inativo para reservar {$nomeProd}.");
            }

            $desc = substr("Reserva de estoque ({$origem} #{$pedido_id})", 0, 255);
            $stmtMov->execute([$prodId, $pedido_id, $qtd, $desc]);
        }

        $this->pdo->prepare("UPDATE pedidos SET estoque_reservado = 1 WHERE id = ?")->execute([$pedido_id]);
    }

    /**
     * Estorna o estoque reservado/baixado de um pedido (idempotente via flag estoque_reservado) e registra 'Estorno'.
     */
    public function estornarEstoquePedido(int $pedido_id, string $motivo = 'Cancelamento de pedido'): bool {
        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtPed = $this->pdo->prepare("SELECT id, estoque_reservado FROM pedidos WHERE id = ? FOR UPDATE");
            $stmtPed->execute([$pedido_id]);
            $pedido = $stmtPed->fetch();

            if (!$pedido || (int) ($pedido['estoque_reservado'] ?? 0) !== 1) {
                if ($iniciadoAqui) {
                    $this->pdo->commit();
                }
                return false;
            }

            $stmtItens = $this->pdo->prepare(
                "SELECT id, produto_id, quantidade, quantidade_real 
                 FROM itens_pedido 
                 WHERE pedido_id = ? 
                 FOR UPDATE"
            );
            $stmtItens->execute([$pedido_id]);
            $itens = $stmtItens->fetchAll();

            $stmtUpdProd = $this->pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?");
            $stmtMov = $this->pdo->prepare(
                "INSERT INTO movimentacoes_estoque (produto_id, pedido_id, tipo, quantidade, descricao) 
                 VALUES (?, ?, 'Estorno', ?, ?)"
            );

            $desc = substr("Estorno Pedido #{$pedido_id}: {$motivo}", 0, 255);

            foreach ($itens as $item) {
                $qtdEstorno = ($item['quantidade_real'] !== null)
                    ? round(floatval($item['quantidade_real']), 3)
                    : round(floatval($item['quantidade']), 3);

                if ($qtdEstorno > 0) {
                    $prodId = (int) $item['produto_id'];
                    $stmtUpdProd->execute([$qtdEstorno, $prodId]);
                    $stmtMov->execute([$prodId, $pedido_id, $qtdEstorno, $desc]);
                }
            }

            $this->pdo->prepare("UPDATE pedidos SET estoque_reservado = 0, semana_ativa_chave = NULL WHERE id = ?")
                ->execute([$pedido_id]);

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

    public function criarPedido($usuario_id, $total_calculado, $forma_pagamento, $itens_processados, $transacao_id = null, $status_pagamento = 'Pendente', $idempotency_key = null, $mp_status = null, $tipo_pedido = 'Extra') {
        $iniciadoAqui = !$this->pdo->inTransaction();
        try {
            if ($iniciadoAqui) {
                $this->pdo->beginTransaction();
            }

            if (empty($itens_processados) || !is_array($itens_processados)) {
                throw new Exception("O pedido deve conter ao menos um item válido.");
            }

            // Serializa a criação de pedidos para o mesmo usuário na transação
            $stmtLockUser = $this->pdo->prepare("SELECT id FROM usuarios WHERE id = ? FOR UPDATE");
            $stmtLockUser->execute([(int) $usuario_id]);
            if (!$stmtLockUser->fetch()) {
                throw new Exception("Usuário não encontrado.");
            }

            if ($status_pagamento !== 'Cancelado' && $this->verificarPedidoExistenteSemana($usuario_id)) {
                throw new Exception("Você já realizou um pedido esta semana. O limite é de apenas um pedido por pessoa.");
            }

            $tiposPermitidos = ['Assinatura', 'Avulso', 'Extra', 'Reposicao'];
            $tipo = in_array($tipo_pedido, $tiposPermitidos, true) ? $tipo_pedido : 'Extra';

            // Inserir o pedido com chave de idempotência e chave única semanal ativa
            $sqlPedido = "INSERT INTO pedidos (
                              usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, 
                              forma_pagamento, transacao_id, idempotency_key, mp_status, estoque_reservado, semana_ativa_chave
                          ) VALUES (
                              ?, ?, ?, 'Em separação', ?, ?, ?, ?, ?, 0,
                              IF(? = 'Cancelado', NULL, CONCAT(?, '_', YEARWEEK(NOW(), 0)))
                          )";
            $stmtPedido = $this->pdo->prepare($sqlPedido);
            $stmtPedido->execute([
                (int) $usuario_id,
                round(floatval($total_calculado), 2),
                $status_pagamento,
                $tipo,
                $forma_pagamento,
                $transacao_id,
                $idempotency_key,
                $mp_status,
                $status_pagamento,
                (int) $usuario_id
            ]);

            $pedido_id = (int) $this->pdo->lastInsertId();

            // Inserir itens do pedido preservando snapshots de nome, unidade e preço unitário no momento da venda
            $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, produto_nome, produto_unidade, quantidade, preco_unitario) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmtItem = $this->pdo->prepare($sqlItem);
            $stmtSnapProd = $this->pdo->prepare("SELECT nome, unidade FROM produtos WHERE id = ?");

            foreach ($itens_processados as $ip) {
                $prodId = (int) $ip['produto_id'];
                $snapNome = $ip['produto_nome'] ?? null;
                $snapUnidade = $ip['produto_unidade'] ?? null;

                if ($snapNome === null || $snapUnidade === null) {
                    $stmtSnapProd->execute([$prodId]);
                    $snap = $stmtSnapProd->fetch();
                    if ($snap) {
                        $snapNome = $snapNome ?? $snap['nome'];
                        $snapUnidade = $snapUnidade ?? $snap['unidade'];
                    }
                }

                $stmtItem->execute([
                    $pedido_id,
                    $prodId,
                    $snapNome !== null ? substr((string) $snapNome, 0, 100) : null,
                    $snapUnidade !== null ? substr((string) $snapUnidade, 0, 20) : null,
                    round(floatval($ip['quantidade']), 3),
                    round(floatval($ip['preco_unitario']), 2)
                ]);
            }

            // Reserva de estoque transacional com UPDATE condicional
            if ($status_pagamento !== 'Cancelado') {
                $this->reservarEstoqueItens($pedido_id, $itens_processados, "Pedido {$tipo}");
            }

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }

            return $pedido_id;

        } catch (PDOException $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $sqlState = $e->getCode();
            $driverCode = $e->errorInfo[1] ?? null;
            if (($sqlState === '23000' || $driverCode === 1062) && strpos($e->getMessage(), 'uq_pedidos_usuario_semana_ativa') !== false) {
                throw new Exception("Você já realizou um pedido esta semana. O limite é de apenas um pedido por pessoa.");
            }
            throw $e;
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
                "SELECT id, usuario_id, valor_total, status_pagamento, status_entrega, tipo_pedido, fatura_id, estoque_reservado,
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

            $stmtItens = $this->pdo->prepare(
                "SELECT id, produto_id, produto_nome, produto_unidade, quantidade, preco_unitario 
                 FROM itens_pedido 
                 WHERE pedido_id = ? 
                 FOR UPDATE"
            );
            $stmtItens->execute([$pedidoId]);
            $itensBanco = $stmtItens->fetchAll();

            if (empty($itensBanco)) {
                throw new Exception("O pedido não possui itens válidos para separação.", 400);
            }

            $mapaItens = [];
            foreach ($itensBanco as $ib) {
                $mapaItens[(int) $ib['id']] = [
                    'produto_id' => (int) $ib['produto_id'],
                    'produto_nome' => $ib['produto_nome'],
                    'produto_unidade' => $ib['produto_unidade'],
                    'quantidade' => round(floatval($ib['quantidade']), 3),
                    'preco_unitario' => floatval($ib['preco_unitario'])
                ];
            }

            $estoqueJaReservado = ((int) ($pedido['estoque_reservado'] ?? 0) === 1);
            $itensProcessadosIds = [];
            $novoTotalPedido = 0.0;

            $stmtUpdateItem = $this->pdo->prepare(
                "UPDATE itens_pedido 
                 SET produto_id = ?, produto_nome = COALESCE(?, produto_nome), produto_unidade = COALESCE(?, produto_unidade),
                     quantidade_real = ?, preco_unitario = ?, preco_real = ? 
                 WHERE id = ? AND pedido_id = ?"
            );
            $stmtUpdEstoqueAdd = $this->pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?");
            $stmtUpdEstoqueSub = $this->pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ? AND estoque_atual >= ?");
            $stmtMovEstoque = $this->pdo->prepare(
                "INSERT INTO movimentacoes_estoque (produto_id, pedido_id, tipo, quantidade, descricao) VALUES (?, ?, ?, ?, ?)"
            );
            $stmtBuscaSubMap = $this->pdo->prepare("SELECT produto_substituto_id FROM substituicoes_produto WHERE produto_original_id = ? LIMIT 1");
            $stmtBuscaProd = $this->pdo->prepare("SELECT id, nome, unidade, preco, ativo FROM produtos WHERE id = ? FOR UPDATE");

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

                $rawStr = str_replace(',', '.', trim((string) $item['quantidade_real']));
                if (!preg_match('/^-?\d+(\.\d{1,3})?$/', $rawStr)) {
                    throw new Exception("Precisão decimal inválida na pesagem do item #{$itemId}.", 400);
                }

                $qReal = round(floatval($item['quantidade_real']), 3);
                if ($qReal < 0) {
                    throw new Exception("Quantidade real não pode ser negativa.", 400);
                }

                $infoOriginal = $mapaItens[$itemId];
                $prodIdFinal = $infoOriginal['produto_id'];
                $snapNomeFinal = $infoOriginal['produto_nome'];
                $snapUnidadeFinal = $infoOriginal['produto_unidade'];
                $qTeorica = $infoOriginal['quantidade'];
                $precoUnitario = $infoOriginal['preco_unitario'];

                $solicitaSubstituicao = ($qReal == 0.0) && (!empty($item['substituto_produto_id']) || !empty($item['substituir_falta']));

                if ($solicitaSubstituicao) {
                    $subProdId = !empty($item['substituto_produto_id']) ? (int) $item['substituto_produto_id'] : 0;
                    if ($subProdId <= 0) {
                        $stmtBuscaSubMap->execute([$prodIdFinal]);
                        $subProdId = (int) ($stmtBuscaSubMap->fetchColumn() ?: 0);
                    }
                    if ($subProdId <= 0) {
                        throw new Exception("Nenhum produto substituto configurado para o item #{$itemId}.", 400);
                    }

                    $stmtBuscaProd->execute([$subProdId]);
                    $subProd = $stmtBuscaProd->fetch();
                    if (!$subProd || (int) ($subProd['ativo'] ?? 1) !== 1) {
                        throw new Exception("Produto substituto inválido ou inativo para o item #{$itemId}.", 400);
                    }

                    $qSubstituto = isset($item['quantidade_substituto']) && is_numeric($item['quantidade_substituto'])
                        ? round(floatval($item['quantidade_substituto']), 3)
                        : $qTeorica;
                    if ($qSubstituto <= 0) {
                        throw new Exception("Quantidade do produto substituto deve ser maior que zero.", 400);
                    }

                    // 1. Estorna a reserva do produto original em falta
                    if ($estoqueJaReservado && $qTeorica > 0) {
                        $stmtUpdEstoqueAdd->execute([$qTeorica, $prodIdFinal]);
                        $stmtMovEstoque->execute([
                            $prodIdFinal,
                            $pedidoId,
                            'Estorno',
                            $qTeorica,
                            substr("Estorno por falta/substituição na separação (Pedido #{$pedidoId})", 0, 255)
                        ]);
                    }

                    // 2. Baixa condicionalmente o estoque do produto substituto
                    $stmtUpdEstoqueSub->execute([$qSubstituto, $subProdId, $qSubstituto]);
                    if ($stmtUpdEstoqueSub->rowCount() === 0) {
                        throw new Exception("Estoque insuficiente para o produto substituto " . $subProd['nome'] . ".", 400);
                    }
                    $stmtMovEstoque->execute([
                        $subProdId,
                        $pedidoId,
                        'Saída',
                        $qSubstituto,
                        substr("Saída definitiva por substituição na separação (Pedido #{$pedidoId})", 0, 255)
                    ]);

                    $prodIdFinal = $subProdId;
                    $snapNomeFinal = $subProd['nome'];
                    $snapUnidadeFinal = $subProd['unidade'];
                    $qReal = $qSubstituto;
                    if (!($pedido['tipo_pedido'] === 'Assinatura' && $precoUnitario == 0.0)) {
                        $precoUnitario = floatval($subProd['preco']);
                    }
                } else {
                    // Fluxo normal de pesagem ou falta sem substituição
                    if ($estoqueJaReservado) {
                        $diferenca = round($qReal - $qTeorica, 3);
                        if ($diferenca < 0) {
                            $qtdEstorno = round($qTeorica - $qReal, 3);
                            $stmtUpdEstoqueAdd->execute([$qtdEstorno, $prodIdFinal]);
                            $motivoEstorno = ($qReal == 0.0)
                                ? "Estorno por falta total na separação (Pedido #{$pedidoId})"
                                : "Estorno de diferença a menor na pesagem (Pedido #{$pedidoId})";
                            $stmtMovEstoque->execute([$prodIdFinal, $pedidoId, 'Estorno', $qtdEstorno, substr($motivoEstorno, 0, 255)]);
                        } elseif ($diferenca > 0) {
                            $stmtUpdEstoqueSub->execute([$diferenca, $prodIdFinal, $diferenca]);
                            if ($stmtUpdEstoqueSub->rowCount() === 0) {
                                throw new Exception("Estoque insuficiente para cobrir o peso adicional do item #{$itemId}.", 400);
                            }
                        }

                        if ($qReal > 0) {
                            $stmtMovEstoque->execute([
                                $prodIdFinal,
                                $pedidoId,
                                'Saída',
                                $qReal,
                                substr("Saída confirmada na separação/pesagem (Pedido #{$pedidoId})", 0, 255)
                            ]);
                        }
                    } else {
                        // Pedido legado sem reserva prévia: baixa o peso real diretamente
                        if ($qReal > 0) {
                            $stmtUpdEstoqueSub->execute([$qReal, $prodIdFinal, $qReal]);
                            if ($stmtUpdEstoqueSub->rowCount() === 0) {
                                throw new Exception("Estoque insuficiente para separar o item #{$itemId}.", 400);
                            }
                            $stmtMovEstoque->execute([
                                $prodIdFinal,
                                $pedidoId,
                                'Saída',
                                $qReal,
                                substr("Saída na separação/pesagem (Pedido #{$pedidoId})", 0, 255)
                            ]);
                        }
                    }
                }

                $precoReal = round($qReal * $precoUnitario, 2);
                $novoTotalPedido += $precoReal;

                $stmtUpdateItem->execute([$prodIdFinal, $snapNomeFinal, $snapUnidadeFinal, $qReal, $precoUnitario, $precoReal, $itemId, $pedidoId]);
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
                     estoque_reservado = 1,
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
        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $sql = "UPDATE pedidos 
                    SET status_pagamento = ?,
                        mp_status = ?,
                        transacao_id = COALESCE(?, transacao_id),
                        forma_pagamento = COALESCE(?, forma_pagamento),
                        idempotency_key = IF(? = 1, NULL, idempotency_key),
                        semana_ativa_chave = IF(? = 'Cancelado', NULL, semana_ativa_chave)
                    WHERE id = ? AND usuario_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $status_pagamento,
                $mp_status,
                $transacao_id,
                $forma_pagamento,
                $limpar_idempotencia ? 1 : 0,
                $status_pagamento,
                (int) $pedido_id,
                (int) $usuario_id
            ]);

            if ($status_pagamento === 'Cancelado') {
                $this->estornarEstoquePedido((int) $pedido_id, "Pagamento Mercado Pago ({$mp_status})");
            }

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }
            return $ok;
        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>