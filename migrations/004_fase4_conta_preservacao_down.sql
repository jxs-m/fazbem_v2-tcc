-- ==============================================================================
-- MIGRAÇÃO 004 (DOWN) — Rollback da Fase 4: Conta e Preservação Histórica
-- ==============================================================================

-- 1. Remover tabelas de recuperação de senha e auditoria de segurança
DROP TABLE IF EXISTS `auditoria_seguranca`;
DROP TABLE IF EXISTS `recuperacao_senha_tokens`;

-- 2. Reverter chaves estrangeiras para a definição anterior
ALTER TABLE `movimentacoes_estoque`
  DROP FOREIGN KEY `fk_movimentacoes_produto`;

ALTER TABLE `movimentacoes_estoque`
  ADD CONSTRAINT `fk_movimentacoes_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

ALTER TABLE `transacoes_financeiras`
  DROP FOREIGN KEY `fk_transacoes_usuario`;

ALTER TABLE `transacoes_financeiras`
  ADD CONSTRAINT `fk_transacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `faturas_mensais`
  DROP FOREIGN KEY `fk_faturas_usuario`;

ALTER TABLE `faturas_mensais`
  ADD CONSTRAINT `fk_faturas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `pedidos`
  DROP FOREIGN KEY `fk_pedidos_usuario`;

ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `itens_pedido`
  DROP FOREIGN KEY `fk_itens_pedido`,
  DROP FOREIGN KEY `fk_itens_produto`;

ALTER TABLE `itens_pedido`
  ADD CONSTRAINT `fk_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

-- 3. Remover colunas de snapshot em itens_pedido e session_version em usuarios
ALTER TABLE `itens_pedido`
  DROP COLUMN `produto_unidade`,
  DROP COLUMN `produto_nome`;

ALTER TABLE `usuarios`
  DROP COLUMN `session_version`;
