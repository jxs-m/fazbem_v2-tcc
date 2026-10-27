-- ==============================================================================
-- MIGRAÇÃO 001 (DOWN) — Rollback da Fase 1: Integridade Financeira e Mercado Pago
-- ==============================================================================

-- 1. Reverter alterações na tabela pedidos
ALTER TABLE `pedidos`
  DROP FOREIGN KEY `fk_pedidos_fatura`,
  DROP INDEX `idx_pedidos_fatura`,
  DROP INDEX `uq_pedidos_idempotency`,
  DROP INDEX `idx_pedidos_transacao`,
  DROP COLUMN `mp_status`,
  DROP COLUMN `idempotency_key`,
  DROP COLUMN `fatura_id`;

-- 2. Reverter alterações na tabela faturas_mensais
ALTER TABLE `faturas_mensais`
  DROP INDEX `uq_faturas_usuario_mes`,
  DROP INDEX `uq_faturas_idempotency`,
  DROP INDEX `idx_faturas_transacao`,
  DROP COLUMN `mp_status`,
  DROP COLUMN `idempotency_key`;
