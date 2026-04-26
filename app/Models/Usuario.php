<?php
// Caminho: app/Models/Usuario.php

require_once __DIR__ . '/../Database.php';

class Usuario {
    private $pdo;

    public function __construct() {
        
        $this->pdo = Database::getConexao();
    }

    
    public function buscarPorEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(); 
    }

    
    public function cadastrarCliente($nome, $email, $senhaCriptografada, $telefone, $endereco, $referencia, $frequencia, $latitude = null, $longitude = null) {
        try {
            
            $this->pdo->beginTransaction();

            
            $sqlUser = "INSERT INTO usuarios (nome, email, senha, telefone, endereco, ponto_referencia, tipo_usuario) 
                        VALUES (?, ?, ?, ?, ?, ?, 'cliente')";
            $stmtUser = $this->pdo->prepare($sqlUser);
            $stmtUser->execute([$nome, $email, $senhaCriptografada, $telefone, $endereco, $referencia]);
            
            
            $usuarioId = $this->pdo->lastInsertId();

            if ($latitude !== null && $longitude !== null) {
                $sqlEndereco = "INSERT INTO enderecos (usuario_id, logradouro, ponto_referencia, is_principal, latitude, longitude) 
                                VALUES (?, ?, ?, 1, ?, ?)";
                $stmtEndereco = $this->pdo->prepare($sqlEndereco);
                $stmtEndereco->execute([$usuarioId, $endereco, $referencia, $latitude, $longitude]);
            }

            
            if (!empty($frequencia)) {
                $sqlAssinatura = "INSERT INTO assinaturas (usuario_id, frequencia, status) VALUES (?, ?, 'Ativa')";
                $stmtAssinatura = $this->pdo->prepare($sqlAssinatura);
                $stmtAssinatura->execute([$usuarioId, $frequencia]);
            }

            
            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
        
            $this->pdo->rollBack();
            throw $e; 
        }
    }

   
    public function salvarAdmin($nome, $email, $senhaCriptografada) {
        $usuarioExistente = $this->buscarPorEmail($email);

        if ($usuarioExistente) {
           
            $sql = "UPDATE usuarios SET senha = ?, tipo_usuario = 'admin' WHERE email = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$senhaCriptografada, $email]);
            return 'atualizado';
        } else {
          
            $sql = "INSERT INTO usuarios (nome, email, senha, telefone, endereco, tipo_usuario) 
                    VALUES (?, ?, ?, '00000000000', 'Sistema', 'admin')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$nome, $email, $senhaCriptografada]);
            return 'criado';
        }
    }

    public function buscarPorId($id) {
        $sql = "SELECT id, nome, email, telefone, endereco, ponto_referencia 
                FROM usuarios WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function atualizarPerfil($id, $nome, $telefone, $endereco, $referencia, $senhaHash = null) {
        if ($senhaHash) {
            // Se o cliente digitou uma senha nova, atualiza tudo
            $sql = "UPDATE usuarios SET nome = ?, telefone = ?, endereco = ?, ponto_referencia = ?, senha = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $telefone, $endereco, $referencia, $senhaHash, $id]);
        } else {
            // Se a senha veio em branco, atualiza só os dados de contato
            $sql = "UPDATE usuarios SET nome = ?, telefone = ?, endereco = ?, ponto_referencia = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $telefone, $endereco, $referencia, $id]);
        }
    }

    public function cadastrarMembroEquipe($nome, $email, $senhaCriptografada, $telefone, $tipo_usuario) {
        $sql = "INSERT INTO usuarios (nome, email, senha, telefone, endereco, tipo_usuario) 
                VALUES (?, ?, ?, ?, 'Sistema', ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nome, $email, $senhaCriptografada, $telefone, $tipo_usuario]);
    }
}
?>