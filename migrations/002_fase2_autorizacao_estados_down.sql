-- ==============================================================================
-- MIGRAÇÃO 002 (DOWN) — Rollback da Fase 2: Autorização e Máquina de Estados
-- ==============================================================================

ALTER TABLE `rotas`
  DROP INDEX `idx_rotas_entregador_data_status`;

DROP TABLE IF EXISTS `historico_status_pedidos`;
