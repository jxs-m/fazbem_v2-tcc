-- ==============================================================================
-- MIGRAÇÃO 002 (UP) — Fase 2: Autorização e Máquina de Estados (Auditoria)
-- Reversível por: migrations/002_fase2_autorizacao_estados_down.sql
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `historico_status_pedidos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` INT(11) NOT NULL,
  `usuario_id` INT(11) DEFAULT NULL,
  `papel_usuario` VARCHAR(30) DEFAULT NULL,
  `campo` ENUM('status_entrega', 'status_pagamento') NOT NULL,
  `estado_anterior` VARCHAR(50) NOT NULL,
  `estado_novo` VARCHAR(50) NOT NULL,
  `motivo` VARCHAR(255) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_pedido` (`pedido_id`),
  KEY `idx_hist_usuario` (`usuario_id`),
  CONSTRAINT `fk_hist_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice composto em rotas para validação rápida de atribuição e data por entregador
ALTER TABLE `rotas`
  ADD KEY `idx_rotas_entregador_data_status` (`entregador_id`, `data_rota`, `status`);
