-- ==============================================================================
-- MIGRAÇÃO 004 (UP) — Fase 4: Recuperação de Senha, Sessão e Preservação Histórica
-- Reversível por: migrations/004_fase4_conta_preservacao_down.sql
-- ==============================================================================

-- 1. Controle de versão de sessão para invalidação imediata após troca/reset de senha
ALTER TABLE `usuarios`
  ADD COLUMN `session_version` INT(11) NOT NULL DEFAULT 1 AFTER `saldo_compensacao`;

-- 2. Snapshots de nome e unidade nos itens de pedido para preservação histórica de vendas
ALTER TABLE `itens_pedido`
  ADD COLUMN `produto_nome` VARCHAR(100) DEFAULT NULL AFTER `produto_id`,
  ADD COLUMN `produto_unidade` VARCHAR(20) DEFAULT NULL AFTER `produto_nome`;

UPDATE `itens_pedido` i
JOIN `produtos` p ON i.`produto_id` = p.`id`
SET i.`produto_nome` = p.`nome`,
    i.`produto_unidade` = p.`unidade`
WHERE i.`produto_nome` IS NULL;

-- 3. Substituição de cascatas destrutivas (ON DELETE CASCADE) por ON DELETE RESTRICT
ALTER TABLE `itens_pedido`
  DROP FOREIGN KEY `fk_itens_pedido`,
  DROP FOREIGN KEY `fk_itens_produto`;

ALTER TABLE `itens_pedido`
  ADD CONSTRAINT `fk_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE RESTRICT;

ALTER TABLE `pedidos`
  DROP FOREIGN KEY `fk_pedidos_usuario`;

ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

ALTER TABLE `faturas_mensais`
  DROP FOREIGN KEY `fk_faturas_usuario`;

ALTER TABLE `faturas_mensais`
  ADD CONSTRAINT `fk_faturas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

ALTER TABLE `transacoes_financeiras`
  DROP FOREIGN KEY `fk_transacoes_usuario`;

ALTER TABLE `transacoes_financeiras`
  ADD CONSTRAINT `fk_transacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

ALTER TABLE `movimentacoes_estoque`
  DROP FOREIGN KEY `fk_movimentacoes_produto`;

ALTER TABLE `movimentacoes_estoque`
  ADD CONSTRAINT `fk_movimentacoes_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE RESTRICT;

-- 4. Tabela de tokens de recuperação de senha (armazena exclusivamente o hash SHA-256)
CREATE TABLE IF NOT EXISTS `recuperacao_senha_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `canal` VARCHAR(30) NOT NULL DEFAULT 'email',
  `ip_solicitante` VARCHAR(45) DEFAULT NULL,
  `expira_em` DATETIME NOT NULL,
  `usado_em` DATETIME DEFAULT NULL,
  `invalidado_em` DATETIME DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rec_token_hash` (`token_hash`),
  KEY `idx_rec_usuario_ativo` (`usuario_id`, `usado_em`, `invalidado_em`, `expira_em`),
  CONSTRAINT `fk_rec_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabela de auditoria de eventos de segurança da conta (sem armazenamento de tokens ou senhas)
CREATE TABLE IF NOT EXISTS `auditoria_seguranca` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) DEFAULT NULL,
  `evento` VARCHAR(60) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `detalhes` VARCHAR(255) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aud_seg_usuario` (`usuario_id`),
  CONSTRAINT `fk_aud_seg_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
