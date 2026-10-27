-- ==============================================================================
-- SISTEMA CLUBE FAZBEM V2 — ESTRUTURA DO BANCO DE DADOS (DDL)
-- Trabalho de Conclusão de Curso (TCC) — IFFAR Campus Uruguaiana
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `fazbem_v2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fazbem_v2`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. TABELA: usuarios
-- Armazena os usuários de todos os papéis (cliente, admin, entregador, separador)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `cpf` VARCHAR(14) DEFAULT NULL,
  `endereco` TEXT NOT NULL,
  `ponto_referencia` VARCHAR(255) DEFAULT NULL,
  `tipo_usuario` ENUM('cliente', 'admin', 'entregador', 'separador') DEFAULT 'cliente',
  `saldo_compensacao` DECIMAL(10,2) DEFAULT 0.00,
  `aceite_privacidade_em` DATETIME DEFAULT NULL,
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. TABELA: enderecos
-- Coordenadas de georreferenciamento e endereços dos clientes para o Leaflet.js
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `enderecos`;
CREATE TABLE `enderecos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `logradouro` VARCHAR(255) NOT NULL,
  `ponto_referencia` VARCHAR(255) DEFAULT NULL,
  `is_principal` TINYINT(1) DEFAULT 0,
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(10,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enderecos_usuario` (`usuario_id`),
  CONSTRAINT `fk_enderecos_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. TABELA: assinaturas
-- Controle de periodicidade, status e valor do plano de cada cliente
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `assinaturas`;
CREATE TABLE `assinaturas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `frequencia` ENUM('Semanal', 'Quinzenal') NOT NULL,
  `valor_mensal` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `status` ENUM('Ativa', 'Pausada', 'Cancelada') DEFAULT 'Ativa',
  `ultima_pausa` DATETIME DEFAULT NULL,
  `atualizado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assinaturas_usuario` (`usuario_id`),
  CONSTRAINT `fk_assinaturas_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. TABELA: produtos
-- Catálogo de hortaliças, frutas e legumes orgânicos
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `produtos`;
CREATE TABLE `produtos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `categoria` ENUM('Legumes', 'Verduras', 'Frutas', 'Processados', 'Outros') NOT NULL,
  `tipo_venda` ENUM('Inteiro', 'Fracionado') NOT NULL DEFAULT 'Inteiro',
  `preco` DECIMAL(10,2) NOT NULL,
  `unidade` VARCHAR(20) NOT NULL,
  `estoque_atual` DECIMAL(10,3) DEFAULT 0.000,
  `imagem_url` VARCHAR(255) DEFAULT NULL,
  `peso_estimado_g` INT(11) DEFAULT 0,
  `temporario` TINYINT(1) DEFAULT 0,
  `duracao_dias` INT(11) DEFAULT NULL,
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. TABELA: rotas
-- Rotas de entrega atribuídas a entregadores
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `rotas`;
CREATE TABLE `rotas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entregador_id` INT(11) DEFAULT NULL,
  `data_rota` DATE NOT NULL,
  `status` ENUM('Pendente', 'Rota Iniciada', 'Concluída') DEFAULT 'Pendente',
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rotas_entregador` (`entregador_id`),
  KEY `idx_rotas_entregador_data_status` (`entregador_id`, `data_rota`, `status`),
  CONSTRAINT `fk_rotas_entregador` FOREIGN KEY (`entregador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. TABELA: pedidos
-- Pedidos gerados (assinatura semanal ou compras avulsas/extras)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE `pedidos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `valor_total` DECIMAL(10,2) NOT NULL,
  `status_pagamento` ENUM('Pendente', 'Pago', 'Cancelado') DEFAULT 'Pendente',
  `status_entrega` ENUM('Em separação', 'Aguardando Entrega', 'Saiu para entrega', 'Entregue') DEFAULT 'Em separação',
  `obs_pontual` TEXT DEFAULT NULL,
  `data_entrega` DATE DEFAULT NULL,
  `data_pedido` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_pedido` ENUM('Assinatura', 'Avulso', 'Extra', 'Reposicao') DEFAULT 'Avulso',
  `rota_id` INT(11) DEFAULT NULL,
  `fatura_id` INT(11) DEFAULT NULL,
  `ordem_entrega` INT(11) DEFAULT 9999,
  `entregue_em` TIMESTAMP NULL DEFAULT NULL,
  `transacao_id` VARCHAR(100) DEFAULT NULL,
  `forma_pagamento` VARCHAR(50) DEFAULT NULL,
  `idempotency_key` VARCHAR(100) DEFAULT NULL,
  `mp_status` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_usuario` (`usuario_id`),
  KEY `idx_pedidos_rota` (`rota_id`),
  KEY `idx_pedidos_fatura` (`fatura_id`),
  UNIQUE KEY `uq_pedidos_idempotency` (`idempotency_key`),
  KEY `idx_pedidos_transacao` (`transacao_id`),
  CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pedidos_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pedidos_fatura` FOREIGN KEY (`fatura_id`) REFERENCES `faturas_mensais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. TABELA: itens_pedido
-- Produtos vinculados a cada pedido com quantidades teóricas e pesadas
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `itens_pedido`;
CREATE TABLE `itens_pedido` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` INT(11) NOT NULL,
  `produto_id` INT(11) NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL,
  `quantidade_real` DECIMAL(10,3) DEFAULT NULL,
  `preco_unitario` DECIMAL(10,2) NOT NULL,
  `preco_real` DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_itens_pedido` (`pedido_id`),
  KEY `idx_itens_produto` (`produto_id`),
  CONSTRAINT `fk_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. TABELA: faturas_mensais
-- Faturas consolidadas por mês (mensalidade + extras - créditos de compensação)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `faturas_mensais`;
CREATE TABLE `faturas_mensais` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `mes_referencia` VARCHAR(7) NOT NULL,
  `valor_mensalidade` DECIMAL(10,2) NOT NULL,
  `valor_extras` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_desconto_creditos` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_total` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Pendente', 'Pago', 'Cancelado') DEFAULT 'Pendente',
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `pago_em` TIMESTAMP NULL DEFAULT NULL,
  `transacao_id` VARCHAR(100) DEFAULT NULL,
  `forma_pagamento` VARCHAR(50) DEFAULT NULL,
  `idempotency_key` VARCHAR(100) DEFAULT NULL,
  `mp_status` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_faturas_usuario` (`usuario_id`),
  UNIQUE KEY `uq_faturas_usuario_mes` (`usuario_id`, `mes_referencia`),
  UNIQUE KEY `uq_faturas_idempotency` (`idempotency_key`),
  KEY `idx_faturas_transacao` (`transacao_id`),
  CONSTRAINT `fk_faturas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. TABELA: preferencias
-- Restrições de consumo do cliente ("Troca Fixa", "Não Consumo")
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `preferencias`;
CREATE TABLE `preferencias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `tipo` VARCHAR(50) DEFAULT 'Troca Fixa',
  `descricao` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_preferencias_usuario` (`usuario_id`),
  CONSTRAINT `fk_preferencias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. TABELA: movimentacoes_estoque
-- Log de entradas, saídas e descartes de estoque
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `movimentacoes_estoque`;
CREATE TABLE `movimentacoes_estoque` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `produto_id` INT(11) NOT NULL,
  `tipo` ENUM('Entrada', 'Saída', 'Descarte') NOT NULL,
  `quantidade` DECIMAL(10,3) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `data_movimentacao` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movimentacoes_produto` (`produto_id`),
  CONSTRAINT `fk_movimentacoes_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. TABELA: transacoes_financeiras
-- Registro contábil de débitos e créditos (compensações por pausas ou faltas)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `transacoes_financeiras`;
CREATE TABLE `transacoes_financeiras` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `tipo` ENUM('Credito', 'Debito') NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `motivo` VARCHAR(255) NOT NULL,
  `data_transacao` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transacoes_usuario` (`usuario_id`),
  CONSTRAINT `fk_transacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. TABELA: configuracoes
-- Chaves e valores dinâmicos do sistema (ex: texto do Kit da Semana)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `configuracoes`;
CREATE TABLE `configuracoes` (
  `chave` VARCHAR(50) NOT NULL,
  `valor` TEXT DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. TABELA: rate_limits
-- Proteção contra ataques de força bruta gerenciada pelo Security.php
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `attempts` INT(11) NOT NULL DEFAULT 1,
  `first_attempt` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_endpoint` (`ip`, `endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 14. TABELA: substituicoes_produto
-- Mapeamento de produtos substitutos automáticos em caso de falta
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `substituicoes_produto`;
CREATE TABLE `substituicoes_produto` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `produto_original_id` INT(11) NOT NULL,
  `produto_substituto_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_subst_original` (`produto_original_id`),
  KEY `idx_subst_substituto` (`produto_substituto_id`),
  CONSTRAINT `fk_subst_original` FOREIGN KEY (`produto_original_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subst_substituto` FOREIGN KEY (`produto_substituto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. TABELA: historico_status_pedidos
-- Auditoria de transições de status operacional e financeiro de pedidos
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `historico_status_pedidos`;
CREATE TABLE `historico_status_pedidos` (
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

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- CARGA INICIAL BÁSICA (Configurações e Usuário Administrador Padrão)
-- Senha do Administrador padrão: admin123
-- ==============================================================================

INSERT INTO `configuracoes` (`chave`, `valor`) VALUES 
('kit_semana', 'Alface Crespa (1 un), Rúcula Fresca (1 maço), Cenoura (1 kg), Maçã Gala (1 kg), Banana Prata (1 kg)')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

INSERT INTO `usuarios` (`nome`, `email`, `senha`, `telefone`, `endereco`, `ponto_referencia`, `tipo_usuario`) VALUES
('Administrador Faz Bem', 'admin@fazbem.com', '$2y$12$rj106ppk3wDR9InAe1PVFeqXvUNienYD9RIzrZCF0.WKXkR53RWCu', '(55) 99999-0000', 'Sede Faz Bem - Rua Central, 100', 'Sede Administrativa', 'admin')
ON DUPLICATE KEY UPDATE `tipo_usuario` = 'admin';
