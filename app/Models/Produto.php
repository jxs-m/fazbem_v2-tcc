<?php
// Caminho: app/Models/Produto.php


require_once __DIR__ . '/../Database.php';

class Produto {
    private $pdo;

    public function __construct() {
       
        $this->pdo = Database::getConexao();
    }


    public function listarTodos($apenasAtivos = false) {
        if ($apenasAtivos) {
            $sql = "SELECT * FROM produtos WHERE ativo = 1 ORDER BY nome ASC";
        } else {
            $sql = "SELECT * FROM produtos ORDER BY nome ASC";
        }
        $stmt = $this->pdo->query($sql);
        
        return $stmt->fetchAll();
    }


    public function salvar($nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem, $tipo_venda, $temporario = 0, $duracao_dias = null, $ativo = 1) {
        $precoNum = round(floatval($preco), 2);
        $estoqueNum = round(floatval($estoque), 3);
        if ($precoNum < 0 || $estoqueNum < 0) {
            throw new Exception("Preço e estoque não podem ser negativos.");
        }

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $sql = "INSERT INTO produtos (nome, categoria, preco, unidade, estoque_atual, peso_estimado_g, imagem_url, tipo_venda, temporario, duracao_dias, ativo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $nome,
                $categoria,
                $precoNum,
                $unidade,
                $estoqueNum,
                (int) $peso_estimado_g,
                $caminhoImagem,
                $tipo_venda,
                (int) $temporario,
                $duracao_dias,
                (int) $ativo ? 1 : 0
            ]);

            $produtoId = (int) $this->pdo->lastInsertId();
            if ($estoqueNum > 0 && $produtoId > 0) {
                $stmtMov = $this->pdo->prepare(
                    "INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, descricao) VALUES (?, 'Entrada', ?, ?)"
                );
                $stmtMov->execute([$produtoId, $estoqueNum, "Estoque inicial no cadastro do produto"]);
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
    
    public function atualizar($id, $nome, $categoria, $preco, $unidade, $estoque, $peso_estimado_g, $caminhoImagem = null, $tipo_venda = 'Inteiro', $temporario = 0, $duracao_dias = null, $ativo = 1) {
        $precoNum = round(floatval($preco), 2);
        $estoqueNum = round(floatval($estoque), 3);
        if ($precoNum < 0 || $estoqueNum < 0) {
            throw new Exception("Preço e estoque não podem ser negativos.");
        }

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtAtual = $this->pdo->prepare("SELECT estoque_atual FROM produtos WHERE id = ? FOR UPDATE");
            $stmtAtual->execute([(int) $id]);
            $prodAtual = $stmtAtual->fetch();
            if (!$prodAtual) {
                throw new Exception("Produto não encontrado para atualização.");
            }
            $estoqueAnterior = round(floatval($prodAtual['estoque_atual']), 3);

            if ($caminhoImagem) {
                $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ?, imagem_url = ?, tipo_venda = ?, temporario = ?, duracao_dias = ?, ativo = ? WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $ok = $stmt->execute([$nome, $categoria, $precoNum, $unidade, $estoqueNum, (int) $peso_estimado_g, $caminhoImagem, $tipo_venda, (int) $temporario, $duracao_dias, (int) $ativo ? 1 : 0, (int) $id]);
            } else {
                // Se não enviou foto, atualizamos apenas os textos e mantendo a foto antiga
                $sql = "UPDATE produtos SET nome = ?, categoria = ?, preco = ?, unidade = ?, estoque_atual = ?, peso_estimado_g = ?, tipo_venda = ?, temporario = ?, duracao_dias = ?, ativo = ? WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $ok = $stmt->execute([$nome, $categoria, $precoNum, $unidade, $estoqueNum, (int) $peso_estimado_g, $tipo_venda, (int) $temporario, $duracao_dias, (int) $ativo ? 1 : 0, (int) $id]);
            }

            $diferenca = round($estoqueNum - $estoqueAnterior, 3);
            if (abs($diferenca) >= 0.001) {
                $tipoMov = ($diferenca > 0) ? 'Entrada' : 'Saída';
                $stmtMov = $this->pdo->prepare(
                    "INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, descricao) VALUES (?, ?, ?, ?)"
                );
                $stmtMov->execute([
                    (int) $id,
                    $tipoMov,
                    abs($diferenca),
                    "Ajuste direto no cadastro do produto"
                ]);
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

    public function deletar($id) {
        // Exclusão lógica (ativo = 0) para preservar integralmente o histórico de vendas e movimentações
        $sql = "UPDATE produtos SET ativo = 0 WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([(int) $id]);
        return $stmt->rowCount() > 0;
    }
}
?>