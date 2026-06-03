/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.1-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: fazbemdb
-- ------------------------------------------------------
-- Server version	11.8.1-MariaDB-4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `assinaturas`
--

DROP TABLE IF EXISTS `assinaturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assinaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `frequencia` enum('Semanal','Quinzenal') NOT NULL,
  `valor_mensal` decimal(10,2) NOT NULL DEFAULT 100.00,
  `status` enum('Ativa','Pausada','Cancelada') DEFAULT 'Ativa',
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `assinaturas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assinaturas`
--

LOCK TABLES `assinaturas` WRITE;
/*!40000 ALTER TABLE `assinaturas` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `assinaturas` VALUES
(19,29,'Quinzenal',50.00,'Ativa','2026-06-01 23:37:14'),
(20,30,'Semanal',100.00,'Ativa','2026-06-01 23:37:14'),
(21,31,'Semanal',100.00,'Ativa','2026-06-01 23:37:15'),
(22,32,'Semanal',100.00,'Ativa','2026-06-01 23:37:15'),
(23,33,'Quinzenal',50.00,'Ativa','2026-06-01 23:37:15'),
(24,34,'Quinzenal',50.00,'Ativa','2026-06-01 23:37:16'),
(25,35,'Semanal',100.00,'Ativa','2026-06-01 23:37:16'),
(26,36,'Quinzenal',50.00,'Ativa','2026-06-01 23:37:16'),
(27,37,'Semanal',100.00,'Ativa','2026-06-01 23:37:17');
/*!40000 ALTER TABLE `assinaturas` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `configuracoes`
--

DROP TABLE IF EXISTS `configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracoes` (
  `chave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracoes`
--

LOCK TABLES `configuracoes` WRITE;
/*!40000 ALTER TABLE `configuracoes` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `configuracoes` VALUES
('kit_semana','1 unidade de leite moça, 1 unidade de maçã, 1 unidade de Porno gay');
/*!40000 ALTER TABLE `configuracoes` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `enderecos`
--

DROP TABLE IF EXISTS `enderecos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `enderecos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `logradouro` varchar(255) NOT NULL,
  `ponto_referencia` varchar(255) DEFAULT NULL,
  `is_principal` tinyint(1) DEFAULT 0,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(10,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `enderecos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enderecos`
--

LOCK TABLES `enderecos` WRITE;
/*!40000 ALTER TABLE `enderecos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `enderecos` VALUES
(4,35,'Dr Maia 2040, ',NULL,1,-29.75963120,-57.07201870),
(5,34,'iris Valls 2182 ',NULL,1,-29.77115670,-57.09263340),
(6,36,'Marechal Deodoro, 2311 ',NULL,1,-29.77854500,-57.09566150),
(7,37,' Julio de Castilhos 1882',NULL,1,-29.76112420,-57.08316290),
(8,29,'Rua General Vitorino 1853 ',NULL,1,-29.76883070,-57.09110890),
(9,33,'General Vitorino 2038',NULL,1,-29.76883070,-57.09110890),
(10,31,'General Vitorino 1897',NULL,1,-29.76883070,-57.09110890),
(11,32,'General Vitórino 1910',NULL,1,-29.76883070,-57.09110890),
(12,30,'General Vitorino 1897',NULL,1,-29.76883070,-57.09110890);
/*!40000 ALTER TABLE `enderecos` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `faturas_mensais`
--

DROP TABLE IF EXISTS `faturas_mensais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `faturas_mensais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `mes_referencia` varchar(7) NOT NULL,
  `valor_mensalidade` decimal(10,2) NOT NULL,
  `valor_extras` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_desconto_creditos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_total` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Pago','Cancelado') DEFAULT 'Pendente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `pago_em` timestamp NULL DEFAULT NULL,
  `transacao_id` varchar(100) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `faturas_mensais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas_mensais`
--

LOCK TABLES `faturas_mensais` WRITE;
/*!40000 ALTER TABLE `faturas_mensais` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `faturas_mensais` VALUES
(27,29,'2026-06',50.00,0.00,0.00,50.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL),
(28,30,'2026-06',100.00,0.00,0.00,100.00,'Pago','2026-06-02 00:26:43','2026-06-02 23:31:40','1346935489','Mercado Pago - master'),
(29,31,'2026-06',100.00,0.00,0.00,100.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL),
(30,32,'2026-06',100.00,0.00,0.00,100.00,'Pago','2026-06-02 00:26:43','2026-06-02 23:34:06','1346935521','Mercado Pago - master'),
(31,33,'2026-06',50.00,0.00,0.00,50.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL),
(32,34,'2026-06',50.00,0.00,0.00,50.00,'Pago','2026-06-02 00:26:43','2026-06-02 01:26:19','1327317540','Mercado Pago - master'),
(33,35,'2026-06',100.00,0.00,0.00,100.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL),
(34,36,'2026-06',50.00,0.00,0.00,50.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL),
(35,37,'2026-06',100.00,0.00,0.00,100.00,'Pendente','2026-06-02 00:26:43',NULL,NULL,NULL);
/*!40000 ALTER TABLE `faturas_mensais` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `itens_pedido`
--

DROP TABLE IF EXISTS `itens_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `itens_pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `quantidade_real` decimal(10,3) DEFAULT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `preco_real` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `itens_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itens_pedido_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `itens_pedido`
--

LOCK TABLES `itens_pedido` WRITE;
/*!40000 ALTER TABLE `itens_pedido` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `itens_pedido` VALUES
(96,75,31,1.000,1.000,0.00,0.00),
(97,75,30,0.170,0.170,0.00,0.00),
(98,75,32,1.000,1.000,0.00,0.00),
(99,76,31,1.000,1.000,0.00,0.00),
(100,76,30,0.170,0.170,0.00,0.00),
(101,76,32,1.000,1.000,0.00,0.00),
(102,77,31,1.000,NULL,0.00,NULL),
(103,77,30,0.170,NULL,0.00,NULL),
(104,77,32,1.000,NULL,0.00,NULL),
(105,78,31,1.000,NULL,0.00,NULL),
(106,78,30,0.170,NULL,0.00,NULL),
(107,78,32,1.000,NULL,0.00,NULL),
(108,79,31,1.000,NULL,0.00,NULL),
(109,79,30,0.170,NULL,0.00,NULL),
(110,79,32,1.000,NULL,0.00,NULL),
(111,80,31,1.000,NULL,0.00,NULL),
(112,80,30,0.170,NULL,0.00,NULL),
(113,80,32,1.000,NULL,0.00,NULL),
(114,81,31,1.000,NULL,0.00,NULL),
(115,81,30,0.170,NULL,0.00,NULL),
(116,81,32,1.000,NULL,0.00,NULL),
(117,82,31,1.000,NULL,0.00,NULL),
(118,82,30,0.170,NULL,0.00,NULL),
(119,82,32,1.000,NULL,0.00,NULL),
(120,83,31,1.000,NULL,0.00,NULL),
(121,83,30,0.170,NULL,0.00,NULL),
(122,83,32,1.000,NULL,0.00,NULL);
/*!40000 ALTER TABLE `itens_pedido` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `movimentacoes_estoque`
--

DROP TABLE IF EXISTS `movimentacoes_estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentacoes_estoque` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL,
  `tipo` enum('Entrada','Saída','Descarte') NOT NULL,
  `quantidade` decimal(10,3) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_movimentacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `movimentacoes_estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimentacoes_estoque`
--

LOCK TABLES `movimentacoes_estoque` WRITE;
/*!40000 ALTER TABLE `movimentacoes_estoque` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `movimentacoes_estoque` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `status_pagamento` enum('Pendente','Pago','Cancelado') DEFAULT 'Pendente',
  `status_entrega` enum('Em separação','Aguardando Entrega','Saiu para entrega','Entregue') DEFAULT 'Em separação',
  `obs_pontual` text DEFAULT NULL,
  `data_entrega` date DEFAULT NULL,
  `data_pedido` timestamp NULL DEFAULT current_timestamp(),
  `tipo_pedido` enum('Assinatura','Avulso','Extra','Reposicao') DEFAULT 'Avulso',
  `rota_id` int(11) DEFAULT NULL,
  `ordem_entrega` int(11) DEFAULT 9999,
  `entregue_em` timestamp NULL DEFAULT NULL,
  `transacao_id` varchar(100) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `fk_rota` (`rota_id`),
  CONSTRAINT `fk_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `pedidos` VALUES
(75,29,0.00,'Pendente','Entregue',' [Pesado]',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,'2026-06-03 11:03:10',NULL,NULL),
(76,30,0.00,'Pendente','Entregue',' [Pesado]',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,'2026-06-03 11:07:59',NULL,NULL),
(77,31,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(78,32,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(79,33,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(80,34,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(81,35,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(82,36,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL),
(83,37,25.00,'Pendente','Em separação','',NULL,'2026-06-03 01:29:40','Assinatura',NULL,9999,NULL,NULL,NULL);
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `preferencias`
--

DROP TABLE IF EXISTS `preferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `preferencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT 'Troca Fixa',
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `preferencias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencias`
--

LOCK TABLES `preferencias` WRITE;
/*!40000 ALTER TABLE `preferencias` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `preferencias` VALUES
(16,30,'Troca Fixa','Troca tempero por rúcula - não consome lalique'),
(17,35,'Troca Fixa','Atenção nas quantidades 4 alfaces 2 rúculas o restante normal');
/*!40000 ALTER TABLE `preferencias` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `produtos`
--

DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `categoria` enum('Legumes','Verduras','Frutas','Processados','Outros') NOT NULL,
  `tipo_venda` enum('Inteiro','Fracionado') NOT NULL DEFAULT 'Inteiro',
  `preco` decimal(10,2) NOT NULL,
  `unidade` varchar(20) NOT NULL,
  `estoque_atual` decimal(10,3) DEFAULT 0.000,
  `imagem_url` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `peso_estimado_g` int(11) DEFAULT 0,
  `temporario` tinyint(1) DEFAULT 0,
  `duracao_dias` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produtos`
--

LOCK TABLES `produtos` WRITE;
/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `produtos` VALUES
(30,'maçã','Frutas','Inteiro',8.00,'kg',13.470,'uploads/6a1e1a094ea11.png','2026-06-01 23:47:21',170,0,NULL),
(31,'leite moça','Processados','Inteiro',8.00,'L',43.000,'uploads/6a1e1dd353ead.jpg','2026-06-02 00:03:31',1000,0,NULL),
(32,'Porno gay','Outros','Inteiro',67.69,'U',58.000,'uploads/6a1f64315a84d.png','2026-06-02 23:16:01',60000,0,NULL),
(33,'cenoura','Legumes','Inteiro',5.00,'kg',41.000,'uploads/6a1f84d14db84.png','2026-06-03 01:35:13',200,0,NULL),
(34,'alface picada','Processados','Fracionado',4.00,'kg',41.000,'uploads/6a1f856ed8810.png','2026-06-03 01:37:50',0,0,NULL);
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `first_attempt` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_endpoint` (`ip`,`endpoint`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `rate_limits` VALUES
(2,'::1','/faz_bem_v2/api_login_v2.php',2,1780526830),
(3,'10.1.7.75','/faz_bem_v2/api_login_v2.php',4,1780442022),
(4,'10.1.9.102','/faz_bem_v2/api_login_v2.php',1,1780526983);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `rotas`
--

DROP TABLE IF EXISTS `rotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rotas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entregador_id` int(11) DEFAULT NULL,
  `data_rota` date NOT NULL,
  `status` enum('Pendente','Rota Iniciada','Concluída') DEFAULT 'Pendente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entregador_id` (`entregador_id`),
  CONSTRAINT `rotas_ibfk_1` FOREIGN KEY (`entregador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rotas`
--

LOCK TABLES `rotas` WRITE;
/*!40000 ALTER TABLE `rotas` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `rotas` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `substituicoes_produto`
--

DROP TABLE IF EXISTS `substituicoes_produto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `substituicoes_produto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_original_id` int(11) NOT NULL,
  `produto_substituto_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `produto_original_id` (`produto_original_id`),
  KEY `produto_substituto_id` (`produto_substituto_id`),
  CONSTRAINT `substituicoes_produto_ibfk_1` FOREIGN KEY (`produto_original_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `substituicoes_produto_ibfk_2` FOREIGN KEY (`produto_substituto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `substituicoes_produto`
--

LOCK TABLES `substituicoes_produto` WRITE;
/*!40000 ALTER TABLE `substituicoes_produto` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `substituicoes_produto` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `transacoes_financeiras`
--

DROP TABLE IF EXISTS `transacoes_financeiras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transacoes_financeiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('Credito','Debito') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `data_transacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `transacoes_financeiras_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transacoes_financeiras`
--

LOCK TABLES `transacoes_financeiras` WRITE;
/*!40000 ALTER TABLE `transacoes_financeiras` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `transacoes_financeiras` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `endereco` text NOT NULL,
  `ponto_referencia` varchar(255) DEFAULT NULL,
  `tipo_usuario` enum('cliente','admin','entregador','separador') DEFAULT 'cliente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `saldo_compensacao` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `usuarios` VALUES
(28,'Administrador','admin@fazbem.com','$2y$12$N5Iv5I5fj7qvaoXIGipKaeqa5b4apGh2LLB/v2OclX3BbF/T5CKk2','00000000000',NULL,'Sistema',NULL,'admin','2026-06-01 23:33:25',0.00),
(29,'Lila Tellechea Pinto','lila.tellechea.pinto@import.local','$2y$12$Fv7GAybIKsA/N81t/iMiK.SzEgkL1r39.P7EHt/Z.riqna2Y2V3/S','55 996131314','','Rua General Vitorino 1853 ',NULL,'cliente','2026-06-01 23:37:14',0.00),
(30,'Natasha Frasson Pavin','natasha.frasson.pavin@import.local','$2y$12$ZAy7TLPRF26T8YA5m8tnhetDA5l//I400UlSfwtcXMoibPX83PER6','55 999971771','012.860.600-28','General Vitorino 1897',NULL,'cliente','2026-06-01 23:37:14',0.00),
(31,'Luiz Eduardo Medaglia','luiz.eduardo.medaglia@import.local','$2y$12$D60rie934JJ3z4A6Fnd3n.Dk1HKC1uBZ.QZuvt59Ey8LqaZJZ4m8G','55 999666886','011.574.800-80','General Vitorino 1897',NULL,'cliente','2026-06-01 23:37:15',0.00),
(32,'Mauricio Lima Fontoura','mauricio.lima.fontoura@import.local','$2y$12$1Tubc9lvSB4sV78gonxpV.Zs/qWv/cDOtzVKn8DpGqDmj6TMXt/9a','55 996308588','010.413.140-35','General Vitórino 1910',NULL,'cliente','2026-06-01 23:37:15',0.00),
(33,'Lillian Buling Couto Schultz','lillian.buling.couto.schultz@import.local','$2y$12$5wWG1WZR4wsMPZzYZ7H2Z.X.xRxTkehUumUNyorww2BCgykdutFLq','55 996954974','953.407.260-53','General Vitorino 2038',NULL,'cliente','2026-06-01 23:37:15',0.00),
(34,'Elisa de Oliveira Rosa','elisa.de.oliveira.rosa@import.local','$2y$12$8ENI5i1KmmOQlijBzK7j4.ocSYXNVM6gvXzJ4MHgb3HdeyvKuZZeu','55 981423562','028.487.600-36','iris Valls 2182 ',NULL,'cliente','2026-06-01 23:37:16',0.00),
(35,'Carla Uhmann','carla.uhmann@import.local','$2y$12$5y92e5lkKE4cxZQK0mYEPueHs17TMWZuYMXUhpUs5zu7r1Weo9Rcq','55 999980688','533.131.510-00','Dr Maia 2040, ',NULL,'cliente','2026-06-01 23:37:16',0.00),
(36,'Fabiana de Moura Rubim','fabiana.de.moura.rubim@import.local','$2y$12$Yypy88DYzvqnfqouE8LZJ.moPrCKC7Hzqer0aLafLxnHIfZyklE7u','55 981185946','941.626.890-91','Marechal Deodoro, 2311 ',NULL,'cliente','2026-06-01 23:37:16',0.00),
(37,'Leticia Rodhen','leticia.rodhen@import.local','$2y$12$GJxrdSUY.c1uWmAINnPTu.x45vlX6l93Xp66wqxfCwbJE86bdGheO','55 996590923','018.322.210-59',' Julio de Castilhos 1882',NULL,'cliente','2026-06-01 23:37:17',0.00),
(38,'peso','peso@fazbem','$2y$12$mQtK7YTiITWurRhivKQVX.BXkIg/i7pEar3ImDEKglLJnd81jdXCS','55991791570',NULL,'Sistema',NULL,'separador','2026-06-01 23:43:27',0.00),
(39,'entregador','lilo@email.com','$2y$12$gU7WGGwJY3dQcIpUrcBDfOLBeczRGQqNeStsyNR5KwaoEPWL0tr6q','55991791570',NULL,'Sistema',NULL,'entregador','2026-06-01 23:43:52',0.00);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-06-03 20:10:02
