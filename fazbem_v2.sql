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
  `status` enum('Ativa','Pausada','Cancelada') DEFAULT 'Ativa',
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `assinaturas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assinaturas`
--

LOCK TABLES `assinaturas` WRITE;
/*!40000 ALTER TABLE `assinaturas` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `assinaturas` VALUES
(1,2,'Semanal','Ativa','2026-03-30 15:49:18'),
(6,13,'Semanal','Ativa','2026-04-01 02:29:00'),
(7,14,'Quinzenal','Ativa','2026-04-01 02:29:00'),
(8,15,'Semanal','Pausada','2026-04-01 02:30:07');
/*!40000 ALTER TABLE `assinaturas` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enderecos`
--

LOCK TABLES `enderecos` WRITE;
/*!40000 ALTER TABLE `enderecos` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `enderecos` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
(7,9,23,1,7.00);
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
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `fk_rota` (`rota_id`),
  CONSTRAINT `fk_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `pedidos` VALUES
(1,2,5.00,'Pendente','Em separação',NULL,NULL,'2026-03-30 16:34:19','Avulso',NULL),
(2,2,20.00,'Pendente','Em separação',NULL,NULL,'2026-03-30 16:34:32','Avulso',NULL),
(3,2,5.00,'Pendente','Em separação',NULL,NULL,'2026-03-30 16:34:41','Avulso',NULL),
(4,2,30.00,'Pendente','Em separação',NULL,NULL,'2026-03-30 16:34:50','Avulso',NULL),
(5,13,45.50,'Pago','Entregue',NULL,NULL,'2026-03-27 02:29:00','Avulso',NULL),
(6,13,52.00,'Pendente','Saiu para entrega',NULL,NULL,'2026-04-01 02:29:00','Avulso',NULL),
(7,14,110.00,'Pago','Saiu para entrega',NULL,NULL,'2026-03-30 02:29:00','Avulso',NULL),
(9,13,7.00,'Pendente','Saiu para entrega',NULL,NULL,'2026-04-01 12:59:01','Avulso',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencias`
--

LOCK TABLES `preferencias` WRITE;
/*!40000 ALTER TABLE `preferencias` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `preferencias` VALUES
(2,13,'Troca Fixa','exemplo');
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
  `preco` decimal(10,2) NOT NULL,
  `unidade` varchar(20) NOT NULL,
  `estoque_atual` int(11) DEFAULT 0,
  `imagem_url` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
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
(1,'maçã','Frutas',5.00,'kg',-7,'uploads/69ca7fc877243.webp','2026-03-30 13:51:04'),
(18,'Maçã Gala','Frutas',8.50,'kg',50,NULL,'2026-04-01 02:29:00'),
(19,'Banana Prata','Frutas',6.00,'kg',40,NULL,'2026-04-01 02:29:00'),
(20,'Alface Crespa','Verduras',3.50,'un',30,NULL,'2026-04-01 02:29:00'),
(21,'Rúcula Fresca','Verduras',4.00,'maço',25,NULL,'2026-04-01 02:29:00'),
(22,'Cenoura','Legumes',5.50,'kg',60,NULL,'2026-04-01 02:29:00'),
(23,'Batata Inglesa','Legumes',7.00,'kg',99,NULL,'2026-04-01 02:29:00'),
(24,'Cebola Picada','Processados',12.00,'500g',15,NULL,'2026-04-01 02:29:00'),
(25,'Ovos Caipira','Outros',18.00,'dúzia',20,NULL,'2026-04-01 02:29:00');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `endereco` text NOT NULL,
  `ponto_referencia` varchar(255) DEFAULT NULL,
  `tipo_usuario` enum('cliente','admin','entregador') DEFAULT 'cliente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `saldo_compensacao` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `usuarios` VALUES
(1,'Administrador','admin@fazbem.com','$2y$12$VdCu/pYmBHf1jkVDKbcBRufKw3eU2C1x1bLqpZ2Jh5WMHlZ8IJyDy','00000000000','Sistema',NULL,'admin','2026-03-30 14:27:23',0.00),
(2,'Murilo Siqueira Jaques','rodnei@gmail.com','$2y$12$h5EQ3DaXifzYQyWSEpJp7eBY.P5NAdyVwv9a5gy/okxVSgGAua2r6','55991791570','Rua Sete de Setembro, 1865','rcrcrf','cliente','2026-03-30 15:49:18',0.00),
(12,'Admin Faz Bem','admin2@fazbem.com','$2y$12$GvrcwPTctRCjuQ/Bw5G7JuxnnN6riWJ.ERRiU/fXJFRM4Odzw3KSW','(55) 99999-0000','Sede Faz Bem','','admin','2026-04-01 02:29:00',0.00),
(13,'Carlos Silva','carlos@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 98888-1111','Rua das Flores, 123','Casa verde','cliente','2026-04-01 02:29:00',0.00),
(14,'Ana Pereira','ana@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 97777-2222','Av. Principal, 45','Apto 302','cliente','2026-04-01 02:29:00',0.00),
(15,'Marcos Souza','marcos@email.com','$2y$12$3PrQBBZ1bhba8DyGVpJcouGS94RQP4VI/Gaplft9wqvpwp7FuZ7Oq','(55) 96666-3333','Bairro Novo, 90','Perto da padaria','cliente','2026-04-01 02:29:00',0.00);
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

-- Dump completed on 2026-04-25  8:23:18
