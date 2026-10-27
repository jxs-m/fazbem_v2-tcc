-- ==============================================================================
-- MIGRAÇÃO 003 (UP) — Fase 3: Estoque Transacional, Movimentações e Concorrência
-- Reversível por: migrations/003_fase3_estoque_concorrencia_down.sql
-- ==============================================================================

-- 1. Evolução da tabela produtos (flag ativo e índice de catálogo)
ALTER TABLE `produtos`
  ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `duracao_dias`,
  ADD KEY `idx_produtos_ativo` (`ativo`);

-- 2. Evolução da tabela pedidos (controle de reserva de estoque e chave única semanal ativa)
ALTER TABLE `pedidos`
  ADD COLUMN `estoque_reservado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mp_status`,
  ADD COLUMN `semana_ativa_chave` VARCHAR(30) DEFAULT NULL AFTER `estoque_reservado`,
  ADD UNIQUE KEY `uq_pedidos_usuario_semana_ativa` (`semana_ativa_chave`);

-- 3. Evolução da tabela movimentacoes_estoque (suporte a Reserva e Estorno + vínculo com pedido)
ALTER TABLE `movimentacoes_estoque`
  ADD COLUMN `pedido_id` INT(11) DEFAULT NULL AFTER `produto_id`,
  MODIFY COLUMN `tipo` ENUM('Entrada', 'Saída', 'Descarte', 'Reserva', 'Estorno') NOT NULL,
  ADD KEY `idx_movimentacoes_pedido` (`pedido_id`),
  ADD CONSTRAINT `fk_movimentacoes_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE SET NULL;
