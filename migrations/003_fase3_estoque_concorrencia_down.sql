-- ==============================================================================
-- MIGRAÇÃO 003 (DOWN) — Rollback da Fase 3: Estoque e Concorrência
-- ==============================================================================

-- 1. Reverter alterações em movimentacoes_estoque
ALTER TABLE `movimentacoes_estoque`
  DROP FOREIGN KEY `fk_movimentacoes_pedido`,
  DROP INDEX `idx_movimentacoes_pedido`,
  DROP COLUMN `pedido_id`;

DELETE FROM `movimentacoes_estoque` WHERE `tipo` IN ('Reserva', 'Estorno');

ALTER TABLE `movimentacoes_estoque`
  MODIFY COLUMN `tipo` ENUM('Entrada', 'Saída', 'Descarte') NOT NULL;

-- 2. Reverter alterações em pedidos
ALTER TABLE `pedidos`
  DROP INDEX `uq_pedidos_usuario_semana_ativa`,
  DROP COLUMN `semana_ativa_chave`,
  DROP COLUMN `estoque_reservado`;

-- 3. Reverter alterações em produtos
ALTER TABLE `produtos`
  DROP INDEX `idx_produtos_ativo`,
  DROP COLUMN `ativo`;
