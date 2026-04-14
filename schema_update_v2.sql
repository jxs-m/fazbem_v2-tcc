ALTER TABLE usuarios MODIFY COLUMN tipo_usuario enum('cliente','admin','entregador') DEFAULT 'cliente';
ALTER TABLE usuarios ADD COLUMN saldo_compensacao decimal(10,2) DEFAULT 0.00;

ALTER TABLE enderecos ADD COLUMN latitude decimal(10,8) DEFAULT NULL;
ALTER TABLE enderecos ADD COLUMN longitude decimal(10,8) DEFAULT NULL;

ALTER TABLE pedidos ADD COLUMN tipo_pedido enum('Assinatura','Avulso','Extra','Reposicao') DEFAULT 'Avulso';

CREATE TABLE IF NOT EXISTS rotas (
    id int(11) NOT NULL AUTO_INCREMENT,
    entregador_id int(11) DEFAULT NULL,
    data_rota date NOT NULL,
    status enum('Pendente','Rota Iniciada','Concluída') DEFAULT 'Pendente',
    criado_em timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    FOREIGN KEY (entregador_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

ALTER TABLE pedidos ADD COLUMN rota_id int(11) DEFAULT NULL;
ALTER TABLE pedidos ADD CONSTRAINT fk_rota FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id int(11) NOT NULL AUTO_INCREMENT,
    produto_id int(11) NOT NULL,
    tipo enum('Entrada','Saída','Descarte') NOT NULL,
    quantidade int(11) NOT NULL,
    descricao varchar(255) DEFAULT NULL,
    data_movimentacao timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transacoes_financeiras (
    id int(11) NOT NULL AUTO_INCREMENT,
    usuario_id int(11) NOT NULL,
    tipo enum('Credito','Debito') NOT NULL,
    valor decimal(10,2) NOT NULL,
    motivo varchar(255) NOT NULL,
    data_transacao timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS substituicoes_produto (
    id int(11) NOT NULL AUTO_INCREMENT,
    produto_original_id int(11) NOT NULL,
    produto_substituto_id int(11) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (produto_original_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_substituto_id) REFERENCES produtos(id) ON DELETE CASCADE
);
