-- ==============================================================================
-- SISTEMA CLUBE FAZBEM V2 — DADOS DE DEMONSTRAÇÃO (SEEDS PARA APRESENTAÇÃO/BANCA)
-- Trabalho de Conclusão de Curso (TCC) — IFFAR Campus Uruguaiana
--
-- Credenciais de Acesso de Demonstração:
-- - Administrador: admin@fazbem.com      | Senha: admin123
-- - Entregador:    entregador@fazbem.com | Senha: equipe123
-- - Separador:     separador@fazbem.com  | Senha: equipe123
-- - Clientes:      carlos@email.com      | Senha: cliente123
--                  ana@email.com         | Senha: cliente123
--                  marcos@email.com      | Senha: cliente123
--                  carla@email.com       | Senha: cliente123
-- ==============================================================================

USE `fazbem_v2`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. USUÁRIOS DE DEMONSTRAÇÃO (Equipe e Clientes)
-- ------------------------------------------------------------------------------
-- Hashes Bcrypt verificados:
-- $2y$12$rj106ppk3wDR9InAe1PVFeqXvUNienYD9RIzrZCF0.WKXkR53RWCu -> admin123
-- $2y$12$WHOJaNFPbzAQNdMx.hNq5e3y1sTtRZo54VL6H4WDQpd7M18veDcDe -> equipe123
-- $2y$12$jqAZgJVtDFqmSyqOxmLoi.aOfWFmD4/UqLCMvJVVMiLQe/B8Aufd6 -> cliente123

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `endereco`, `ponto_referencia`, `tipo_usuario`, `saldo_compensacao`) VALUES
(1, 'Administrador Faz Bem', 'admin@fazbem.com', '$2y$12$rj106ppk3wDR9InAe1PVFeqXvUNienYD9RIzrZCF0.WKXkR53RWCu', '(55) 99999-0000', '111.111.111-11', 'Sede Faz Bem - Rua Central, 100', 'Sede', 'admin', 0.00),
(2, 'João Entregador', 'entregador@fazbem.com', '$2y$12$WHOJaNFPbzAQNdMx.hNq5e3y1sTtRZo54VL6H4WDQpd7M18veDcDe', '(55) 99999-8888', '222.222.222-22', 'Base Logística Faz Bem', 'Galpão 1', 'entregador', 0.00),
(3, 'Maria Separadora', 'separador@fazbem.com', '$2y$12$WHOJaNFPbzAQNdMx.hNq5e3y1sTtRZo54VL6H4WDQpd7M18veDcDe', '(55) 99999-7777', '333.333.333-33', 'Base Logística Faz Bem', 'Galpão 1', 'separador', 0.00),
(4, 'Carlos Silva', 'carlos@email.com', '$2y$12$jqAZgJVtDFqmSyqOxmLoi.aOfWFmD4/UqLCMvJVVMiLQe/B8Aufd6', '(55) 98888-1111', '529.982.247-25', 'Rua General Vitorino, 1853', 'Casa verde com portão preto', 'cliente', 0.00),
(5, 'Ana Pereira', 'ana@email.com', '$2y$12$jqAZgJVtDFqmSyqOxmLoi.aOfWFmD4/UqLCMvJVVMiLQe/B8Aufd6', '(55) 97777-2222', '384.921.849-00', 'Rua Dr. Maia, 2040', 'Apto 302 - Residencial Central', 'cliente', 0.00),
(6, 'Marcos Souza', 'marcos@email.com', '$2y$12$jqAZgJVtDFqmSyqOxmLoi.aOfWFmD4/UqLCMvJVVMiLQe/B8Aufd6', '(55) 96666-3333', '749.182.930-12', 'Av. Presidente Vargas, 100', 'Próximo à padaria central', 'cliente', 25.00),
(7, 'Carla Uhmann', 'carla@email.com', '$2y$12$jqAZgJVtDFqmSyqOxmLoi.aOfWFmD4/UqLCMvJVVMiLQe/B8Aufd6', '(55) 95555-4444', '918.273.645-33', 'Rua Bento Martins, 1500', 'Portão de grade branca', 'cliente', 0.00)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`), `senha` = VALUES(`senha`), `tipo_usuario` = VALUES(`tipo_usuario`);

-- ------------------------------------------------------------------------------
-- 2. ENDEREÇOS COM COORDENADAS REAIS DE URUGUAIANA / RS (Para Leaflet.js)
-- ------------------------------------------------------------------------------
INSERT INTO `enderecos` (`usuario_id`, `logradouro`, `ponto_referencia`, `is_principal`, `latitude`, `longitude`) VALUES
(4, 'Rua General Vitorino, 1853, Uruguaiana - RS', 'Casa verde com portão preto', 1, -29.76883070, -57.09110890),
(5, 'Rua Dr. Maia, 2040, Uruguaiana - RS', 'Apto 302 - Residencial Central', 1, -29.75963120, -57.07201870),
(6, 'Av. Presidente Vargas, 100, Uruguaiana - RS', 'Próximo à padaria central', 1, -29.76512345, -57.08512345),
(7, 'Rua Bento Martins, 1500, Uruguaiana - RS', 'Portão de grade branca', 1, -29.75432100, -57.08123400);

-- ------------------------------------------------------------------------------
-- 3. ASSINATURAS DOS CLIENTES
-- ------------------------------------------------------------------------------
INSERT INTO `assinaturas` (`usuario_id`, `frequencia`, `valor_mensal`, `status`) VALUES
(4, 'Semanal', 100.00, 'Ativa'),
(5, 'Quinzenal', 50.00, 'Ativa'),
(6, 'Semanal', 100.00, 'Pausada'),
(7, 'Semanal', 100.00, 'Ativa');

-- ------------------------------------------------------------------------------
-- 4. PREFERÊNCIAS / RESTRIÇÕES DOS CLIENTES
-- ------------------------------------------------------------------------------
INSERT INTO `preferencias` (`usuario_id`, `tipo`, `descricao`) VALUES
(4, 'Troca Fixa', 'Não consumo cebola'),
(5, 'Troca Fixa', 'Trocar maçã por rúcula'),
(7, 'Observação', 'Deixar a cesta no hall de entrada com o porteiro');

-- ------------------------------------------------------------------------------
-- 5. CATÁLOGO DE PRODUTOS ORGÂNICOS
-- ------------------------------------------------------------------------------
INSERT INTO `produtos` (`id`, `nome`, `categoria`, `tipo_venda`, `preco`, `unidade`, `estoque_atual`, `peso_estimado_g`, `imagem_url`) VALUES
(1, 'Alface Crespa Orgânica', 'Verduras', 'Inteiro', 3.50, 'un', 40.000, 200, NULL),
(2, 'Rúcula Fresca Orgânica', 'Verduras', 'Inteiro', 4.00, 'maço', 35.000, 150, NULL),
(3, 'Cenoura Orgânica', 'Legumes', 'Fracionado', 5.50, 'kg', 60.000, 80, NULL),
(4, 'Batata Inglesa Orgânica', 'Legumes', 'Fracionado', 7.00, 'kg', 100.000, 200, NULL),
(5, 'Maçã Gala Orgânica', 'Frutas', 'Fracionado', 8.50, 'kg', 50.000, 150, NULL),
(6, 'Banana Prata Orgânica', 'Frutas', 'Fracionado', 6.00, 'kg', 45.000, 120, NULL),
(7, 'Cebola Roxa Orgânica', 'Processados', 'Inteiro', 12.00, 'un', 25.000, 500, NULL),
(8, 'Ovos Caipiras Orgânicos (Dúzia)', 'Outros', 'Inteiro', 18.00, 'un', 20.000, 600, NULL)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`), `preco` = VALUES(`preco`);

-- ------------------------------------------------------------------------------
-- 6. PEDIDOS DA SEMANA E HISTÓRICO (Em Separação, Aguardando Entrega, Entregue)
-- ------------------------------------------------------------------------------
INSERT INTO `pedidos` (`id`, `usuario_id`, `valor_total`, `status_pagamento`, `status_entrega`, `obs_pontual`, `data_pedido`, `tipo_pedido`, `ordem_entrega`, `entregue_em`) VALUES
-- Pedido 1: Carlos Silva — Em separação (Para visualização no Painel do Separador)
(1, 4, 25.00, 'Pago', 'Em separação', 'Cliente não consome cebola (substituir por alface)', NOW(), 'Assinatura', 9999, NULL),

-- Pedido 2: Ana Pereira — Aguardando Entrega (Ordem 1 no Painel do Entregador)
(2, 5, 25.00, 'Pago', 'Aguardando Entrega', NULL, NOW(), 'Assinatura', 1, NULL),

-- Pedido 3: Carla Uhmann — Saiu para entrega (Ordem 2 no Painel do Entregador)
(3, 7, 25.00, 'Pago', 'Saiu para entrega', 'Entregar com o porteiro', NOW(), 'Assinatura', 2, NULL),

-- Pedido 4: Carlos Silva — Entregue semana anterior (Histórico com comprovante)
(4, 4, 25.00, 'Pago', 'Entregue', NULL, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Assinatura', 1, DATE_SUB(NOW(), INTERVAL 6 DAY))
ON DUPLICATE KEY UPDATE `status_entrega` = VALUES(`status_entrega`);

-- ------------------------------------------------------------------------------
-- 7. ITENS DOS PEDIDOS (Composição das cestas)
-- ------------------------------------------------------------------------------
INSERT INTO `itens_pedido` (`pedido_id`, `produto_id`, `quantidade`, `quantidade_real`, `preco_unitario`, `preco_real`) VALUES
-- Itens do Pedido 1 (Carlos Silva - Em separação)
(1, 1, 1.000, NULL, 3.50, NULL), -- Alface Crespa
(1, 2, 1.000, NULL, 4.00, NULL), -- Rúcula
(1, 3, 1.000, NULL, 5.50, NULL), -- Cenoura
(1, 5, 1.000, NULL, 8.50, NULL), -- Maçã
(1, 6, 1.000, NULL, 6.00, NULL), -- Banana

-- Itens do Pedido 2 (Ana Pereira - Aguardando Entrega)
(2, 1, 1.000, 1.000, 3.50, 3.50),
(2, 2, 2.000, 2.000, 4.00, 8.00), -- Troca por rúcula extra
(2, 3, 1.000, 1.050, 5.50, 5.78),
(2, 6, 1.000, 0.980, 6.00, 5.88),

-- Itens do Pedido 3 (Carla Uhmann - Saiu para entrega)
(3, 1, 1.000, 1.000, 3.50, 3.50),
(3, 3, 1.000, 1.000, 5.50, 5.50),
(3, 4, 1.000, 1.200, 7.00, 8.40),
(3, 5, 1.000, 1.000, 8.50, 8.50),

-- Itens do Pedido 4 (Carlos Silva - Entregue semana anterior)
(4, 1, 1.000, 1.000, 3.50, 3.50),
(4, 3, 1.000, 1.000, 5.50, 5.50),
(4, 5, 1.000, 1.000, 8.50, 8.50),
(4, 6, 1.000, 1.000, 6.00, 6.00);

-- ------------------------------------------------------------------------------
-- 8. FATURAS MENSAIS (Para demonstração de Cobrança e Recibo)
-- ------------------------------------------------------------------------------
INSERT INTO `faturas_mensais` (`usuario_id`, `mes_referencia`, `valor_mensalidade`, `valor_extras`, `valor_desconto_creditos`, `valor_total`, `status`, `pago_em`, `forma_pagamento`) VALUES
-- Fatura 1: Carlos Silva — Pendente (Permite demonstrar o botão de checkout/PIX)
(4, DATE_FORMAT(NOW(), '%Y-%m'), 100.00, 0.00, 0.00, 100.00, 'Pendente', NULL, NULL),

-- Fatura 2: Ana Pereira — Paga (Permite demonstrar visualização e impressão do Recibo)
(5, DATE_FORMAT(NOW(), '%Y-%m'), 50.00, 0.00, 0.00, 50.00, 'Pago', NOW(), 'Mercado Pago (PIX)'),

-- Fatura 3: Marcos Souza — Pendente com desconto por crédito de pausa
(6, DATE_FORMAT(NOW(), '%Y-%m'), 100.00, 0.00, 25.00, 75.00, 'Pendente', NULL, NULL);

-- ------------------------------------------------------------------------------
-- 9. TRANSAÇÕES FINANCEIRAS DE CRÉDITO/DÉBITO
-- ------------------------------------------------------------------------------
INSERT INTO `transacoes_financeiras` (`usuario_id`, `tipo`, `valor`, `motivo`, `data_transacao`) VALUES
(6, 'Credito', 25.00, 'Compensação por pausa na semana anterior', NOW());

SET FOREIGN_KEY_CHECKS = 1;
