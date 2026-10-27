-- ==============================================================================
-- MIGRAÇÃO 001 (UP) — Fase 1: Integridade Financeira e Mercado Pago
-- Reversível por: migrations/001_fase1_integridade_financeira_down.sql
-- ==============================================================================

-- 1. Evolução da tabela faturas_mensais (unicidade por cliente/mês e idempotência MP)
ALTER TABLE `faturas_mensais`
  ADD COLUMN `idempotency_key` VARCHAR(100) DEFAULT NULL AFTER `forma_pagamento`,
  ADD COLUMN `mp_status` VARCHAR(50) DEFAULT NULL AFTER `idempotency_key`,
  ADD UNIQUE KEY `uq_faturas_usuario_mes` (`usuario_id`, `mes_referencia`),
  ADD UNIQUE KEY `uq_faturas_idempotency` (`idempotency_key`),
  ADD KEY `idx_faturas_transacao` (`transacao_id`);

-- 2. Evolução da tabela pedidos (vínculo explícito com fatura e idempotência MP)
ALTER TABLE `pedidos`
  ADD COLUMN `fatura_id` INT(11) DEFAULT NULL AFTER `rota_id`,
  ADD COLUMN `idempotency_key` VARCHAR(100) DEFAULT NULL AFTER `forma_pagamento`,
  ADD COLUMN `mp_status` VARCHAR(50) DEFAULT NULL AFTER `idempotency_key`,
  ADD KEY `idx_pedidos_fatura` (`fatura_id`),
  ADD UNIQUE KEY `uq_pedidos_idempotency` (`idempotency_key`),
  ADD KEY `idx_pedidos_transacao` (`transacao_id`),
  ADD CONSTRAINT `fk_pedidos_fatura` FOREIGN KEY (`fatura_id`) REFERENCES `faturas_mensais` (`id`) ON DELETE SET NULL;
