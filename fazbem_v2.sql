/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.1-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: fazbem_v2
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assinaturas`
--

LOCK TABLES `assinaturas` WRITE;
/*!40000 ALTER TABLE `assinaturas` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `assinaturas` VALUES
(1,2,'Semanal',100.00,'Ativa','2026-03-30 15:49:18'),
(6,13,'Semanal',100.00,'Ativa','2026-04-01 02:29:00'),
(7,14,'Semanal',100.00,'Cancelada','2026-05-04 14:07:30'),
(8,15,'Semanal',100.00,'Pausada','2026-04-01 02:30:07'),
(9,16,'Semanal',100.00,'Ativa','2026-05-01 19:50:06'),
(10,18,'Quinzenal',100.00,'Ativa','2026-05-24 07:40:59'),
(11,19,'Semanal',100.00,'Ativa','2026-05-24 07:40:59'),
(12,20,'Semanal',100.00,'Ativa','2026-05-24 07:41:00'),
(13,21,'Semanal',100.00,'Ativa','2026-05-24 07:41:00'),
(14,22,'Quinzenal',100.00,'Ativa','2026-05-24 07:41:00'),
(15,23,'Quinzenal',100.00,'Ativa','2026-05-24 07:41:00'),
(16,24,'Semanal',100.00,'Ativa','2026-05-24 07:41:00'),
(17,25,'Quinzenal',100.00,'Ativa','2026-05-24 07:41:01'),
(18,26,'Semanal',100.00,'Ativa','2026-05-24 07:41:01');
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
('kit_semana','Aguardando definição do administrador...');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enderecos`
--

LOCK TABLES `enderecos` WRITE;
/*!40000 ALTER TABLE `enderecos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `enderecos` VALUES
(1,16,'Rua Monteiro Lobato, 4442, Uruguiana','iffar',1,-29.78189440,-57.10677266),
(2,14,'Av. Principal, 45',NULL,1,-29.76914574,-57.08496094);
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
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `faturas_mensais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas_mensais`
--

LOCK TABLES `faturas_mensais` WRITE;
/*!40000 ALTER TABLE `faturas_mensais` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `faturas_mensais` VALUES
(1,2,'2026-05',100.00,73.50,0.00,173.50,'Pendente','2026-05-23 02:02:39',NULL),
(2,13,'2026-05',100.00,59.00,0.00,159.00,'Pendente','2026-05-23 02:02:39',NULL),
(3,15,'2026-05',100.00,0.00,0.00,100.00,'Pendente','2026-05-23 02:02:39',NULL),
(4,16,'2026-05',100.00,46.50,50.00,96.50,'Pago','2026-05-23 02:02:39','2026-05-23 02:04:01');
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
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `itens_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itens_pedido_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `itens_pedido`
--

LOCK TABLES `itens_pedido` WRITE;
/*!40000 ALTER TABLE `itens_pedido` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `itens_pedido` VALUES
(1,1,1,1,5.00),
(2,2,1,4,5.00),
(3,3,1,1,5.00),
(4,4,1,6,5.00),
(7,9,23,1,7.00),
(8,10,22,1,5.50),
(9,10,23,1,7.00),
(10,11,23,1,7.00),
(11,12,23,1,7.00),
(12,12,22,1,5.50),
(13,12,19,1,6.00),
(14,12,18,1,8.50),
(23,17,19,1,6.00),
(24,17,21,1,4.00),
(25,17,20,1,3.50);
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
  `quantidade` int(11) NOT NULL,
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
  `status_entrega` enum('Em separação','Saiu para entrega','Entregue') DEFAULT 'Em separação',
  `obs_pontual` text DEFAULT NULL,
  `data_entrega` date DEFAULT NULL,
  `data_pedido` timestamp NULL DEFAULT current_timestamp(),
  `tipo_pedido` enum('Assinatura','Avulso','Extra','Reposicao') DEFAULT 'Avulso',
  `rota_id` int(11) DEFAULT NULL,
  `ordem_entrega` int(11) DEFAULT 9999,
  `entregue_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `fk_rota` (`rota_id`),
  CONSTRAINT `fk_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `pedidos` VALUES
(1,2,5.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-03-30 16:34:19','Avulso',NULL,9999,NULL),
(2,2,20.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-03-30 16:34:32','Avulso',NULL,9999,NULL),
(3,2,5.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-03-30 16:34:41','Avulso',NULL,9999,NULL),
(4,2,30.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-03-30 16:34:50','Avulso',NULL,9999,NULL),
(5,13,45.50,'Pago','Entregue',NULL,NULL,'2026-03-27 02:29:00','Avulso',NULL,9999,NULL),
(6,13,52.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-04-01 02:29:00','Avulso',NULL,9999,NULL),
(7,14,110.00,'Pago','Entregue',NULL,NULL,'2026-03-30 02:29:00','Avulso',NULL,9999,NULL),
(9,13,7.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-04-01 12:59:01','Avulso',NULL,9999,NULL),
(10,16,12.50,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-04-25 12:29:58','Avulso',NULL,9999,NULL),
(11,16,7.00,'Pago','Entregue',' [Faturado em 2026-05]',NULL,'2026-04-27 15:42:30','Avulso',NULL,9999,NULL),
(12,16,27.00,'Pago','Em separação',' [Faturado em 2026-05]',NULL,'2026-05-04 11:48:05','Avulso',NULL,2,NULL),
(17,2,13.50,'Pago','Em separação',' [Faturado em 2026-05]',NULL,'2026-05-04 11:52:01','Avulso',NULL,1,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencias`
--

LOCK TABLES `preferencias` WRITE;
/*!40000 ALTER TABLE `preferencias` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `preferencias` VALUES
(2,13,'Troca Fixa','exemplo'),
(3,16,'Troca Fixa','todas'),
(4,2,'Troca Pontual','desliga o freeze a noitche'),
(5,2,'Troca Fixa','nada'),
(6,19,'Troca Fixa','Troca tempero por rúcula - não consome lalique'),
(12,24,'Troca Fixa','Atenção nas quantidades 4 alfaces 2 rúculas o restante normal');
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produtos`
--

LOCK TABLES `produtos` WRITE;
/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `produtos` VALUES
(1,'maçã','Frutas',5.00,'kg',-7,'uploads/69ca7fc877243.webp','2026-03-30 13:51:04',0),
(18,'Maçã Gala','Frutas',8.50,'kg',49,NULL,'2026-04-01 02:29:00',0),
(19,'Banana Prata','Frutas',6.00,'kg',38,NULL,'2026-04-01 02:29:00',0),
(20,'Alface Crespa','Verduras',3.50,'un',29,NULL,'2026-04-01 02:29:00',0),
(21,'Rúcula Fresca','Verduras',4.00,'maço',24,NULL,'2026-04-01 02:29:00',0),
(22,'Cenoura','Legumes',5.50,'kg',58,NULL,'2026-04-01 02:29:00',0),
(23,'Batata Inglesa','Legumes',7.00,'kg',96,NULL,'2026-04-01 02:29:00',0),
(24,'Cebola Picada','Processados',12.00,'500g',15,NULL,'2026-04-01 02:29:00',0),
(25,'Ovos Caipira','Outros',18.00,'dúzia',20,NULL,'2026-04-01 02:29:00',0);
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
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
INSERT INTO `transacoes_financeiras` VALUES
(1,16,'Credito',50.00,'Pausa na Assinatura (Compensação Semanal)','2026-04-27 16:58:17'),
(2,16,'Debito',50.00,'Abatimento na Fatura de 2026-05','2026-05-23 02:02:39');
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
  `tipo_usuario` enum('cliente','admin','entregador') DEFAULT 'cliente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `saldo_compensacao` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `usuarios` VALUES
(1,'Administrador','admin@fazbem.com','$2y$12$VdCu/pYmBHf1jkVDKbcBRufKw3eU2C1x1bLqpZ2Jh5WMHlZ8IJyDy','00000000000',NULL,'Sistema',NULL,'admin','2026-03-30 14:27:23',0.00),
(2,'Murilo Siqueira Jaques','rodnei@gmail.com','$2y$12$h5EQ3DaXifzYQyWSEpJp7eBY.P5NAdyVwv9a5gy/okxVSgGAua2r6','55991791570',NULL,'Rua Sete de Setembro, 1865','rcrcrf','cliente','2026-03-30 15:49:18',0.00),
(12,'Admin Faz Bem','admin2@fazbem.com','$2y$12$GvrcwPTctRCjuQ/Bw5G7JuxnnN6riWJ.ERRiU/fXJFRM4Odzw3KSW','(55) 99999-0000',NULL,'Sede Faz Bem','','admin','2026-04-01 02:29:00',0.00),
(13,'Carlos Silva','carlos@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 98888-1111',NULL,'Rua das Flores, 123','Casa verde','cliente','2026-04-01 02:29:00',0.00),
(14,'Ana Pereira','ana@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 97777-2222',NULL,'Av. Principal, 45','Apto 302','cliente','2026-04-01 02:29:00',0.00),
(15,'Marcos Souza','marcos@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 96666-3333',NULL,'Bairro Novo, 90','Perto da padaria','cliente','2026-04-01 02:29:00',0.00),
(16,'rogerio','rogerin@email.com','$2y$12$/c.5rcb9KC1RG0kgytrBJuHTAK2NdXMrYw4Nc/iQxj5Js79Uv8XGG','55991791570',NULL,'Rua Monteiro Lobato, 4442, Uruguiana','iffar','cliente','2026-04-25 12:29:20',0.00),
(17,'lilo','lilo@email.com','$2y$12$i.gf3k0dFijQH.PPaIH8s.uBXchxr6gnznF07CMcNQ4TTs4hhFp0y','55991791570',NULL,'Sistema',NULL,'entregador','2026-04-26 10:36:02',0.00),
(18,'Lila Tellechea Pinto','lila348@email.com','$2y$12$b.AflEmwB7WKtuCHVIipMO4y.Bxk1CT9AJ7OGNtanp4hPeIT9wSuy','55 996131314',NULL,'Rua General Vitorino 1853 - Ap 1100, CEP 97.501-543',NULL,'cliente','2026-05-24 07:40:59',0.00),
(19,'Natasha Frasson Pavin','natasha212@email.com','$2y$12$rFfc.JAn5uz.Ip.GkBEPNe30pBILx/JZu.nDKROl3uIvGmCFXzW0C','55 999971771','012.860.600-28','General Vitorino 1897 apto 703, CEP 97.501-543',NULL,'cliente','2026-05-24 07:40:59',0.00),
(20,'Luiz Eduardo Medaglia','luiz674@email.com','$2y$12$BuavFk7kDBcLU2Rzi.DT7.CmlcSAOLXf2dNdhOZ6tmdpgpWP0eBQW','55 999666886','011.574.800-80','General Vitorino 1897 apto 202, CEP 97.501-543',NULL,'cliente','2026-05-24 07:41:00',0.00),
(21,'Mauricio Lima Fontoura','mauricio167@email.com','$2y$12$7pjFLk6YflGlpmuiTdD3p.V/H3vm6sW7zJ0AEgKoJ85hJ5uwZiwG6','55 996308588','010.413.140-35','General Vitórino 1910 Apartamento 803, CEP 97.501-610',NULL,'cliente','2026-05-24 07:41:00',0.00),
(22,'Lillian Buling Couto Schultz','lillian308@email.com','$2y$12$Iu3IbLAK729Vq18fLwwBLOuswYDM75357zTSvZfbT.qJELUxZa/qO','55 996954974','953.407.260-53','General Vitorino 2038, CEP 97.501-84',NULL,'cliente','2026-05-24 07:41:00',0.00),
(23,'Elisa de Oliveira Rosa','elisa267@email.com','$2y$12$d1n.H3SXXN.0SsXxEGfvY.3QDuGBGRSEhkLI6SeP/pGYpAQY.HpL2','55 981423562','028.487.600-36','Rua Iris Valls 2182 ap 102, CEP 97.501-758',NULL,'cliente','2026-05-24 07:41:00',0.00),
(24,'Carla Uhmann','carla395@email.com','$2y$12$WJ9peW7yGJOCMdZznCDsheZBUvGvUew6q5eX5ne0vxRIqVrmckGKe','55 999980688','533.131.510-00','Dr Maia 2040, CEP 97.501-676',NULL,'cliente','2026-05-24 07:41:00',0.00),
(25,'Fabiana de Moura Rubim','fabiana669@email.com','$2y$12$EDmyqsHrMlW974/t0oZtnOE5uSNmlZEeFs6MdMzXS//mX5f0iP.rS','55 981185946','941.626.890-91','Marechal Deodoro, 2311 - Apto 702 Ed. Renoir, CEP 97501-771',NULL,'cliente','2026-05-24 07:41:01',0.00),
(26,'Letícia Rodhen','letícia395@email.com','$2y$12$QqrwxdABaXPEJru/SiC3Juy7WQX/NUa2shoHC7H25ooLKeRLdKVLe','55 996590923','018.322.210-59','Rua Julio de Castilho 1882, CEP 97.501-753',NULL,'cliente','2026-05-24 07:41:01',0.00);
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

-- Dump completed on 2026-05-24  4:48:03
