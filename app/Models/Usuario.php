<?php
// Caminho: app/Models/Usuario.php

require_once __DIR__ . '/../Database.php';

class Usuario {
    private $pdo;
    private static $canalNotificacaoCallback = null;

    public function __construct() {
        $this->pdo = Database::getConexao();
    }

    /**
     * Define callback customizado para envio em canal verificado (útil para testes e provedores de e-mail/SMS).
     */
    public static function setCanalNotificacao(?callable $callback = null): void {
        self::$canalNotificacaoCallback = $callback;
    }

    /**
     * Registra evento de auditoria de segurança sem nunca persistir tokens em texto puro ou senhas.
     */
    public function registrarAuditoriaSeguranca(?int $usuario_id, string $evento, ?string $ip = null, ?string $detalhes = null): void {
        $ipFinal = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $sql = "INSERT INTO auditoria_seguranca (usuario_id, evento, ip, detalhes) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuario_id,
            substr(trim($evento), 0, 60),
            $ipFinal !== null ? substr(trim((string) $ipFinal), 0, 45) : null,
            $detalhes !== null ? substr(trim((string) $detalhes), 0, 255) : null
        ]);
    }

    /**
     * Inicia recuperação de senha gerando token aleatório (CSPRNG), persistindo apenas seu hash SHA-256,
     * com expiração curta (15 min), invalidando tokens anteriores e respondendo uniformemente.
     */
    public function solicitarRecuperacaoSenha(string $email, ?string $ip = null): array {
        $emailLimpo = trim($email);
        $ipCliente = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $mensagemUniforme = 'Se os dados informados corresponderem a uma conta cadastrada, as instruções de recuperação foram enviadas para o canal verificado.';

        $user = ($emailLimpo !== '' && filter_var($emailLimpo, FILTER_VALIDATE_EMAIL))
            ? $this->buscarPorEmail($emailLimpo)
            : false;

        if (!$user) {
            $this->registrarAuditoriaSeguranca(null, 'solicitar_recuperacao', $ipCliente, 'Solicitação processada (conta não localizada)');
            return ['success' => true, 'message' => $mensagemUniforme];
        }

        $usuarioId = (int) $user['id'];
        $rawToken = bin2hex(random_bytes(16)); // 128 bits de entropia
        $tokenHash = hash('sha256', $rawToken);

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            // Invalida tokens anteriores ainda não utilizados deste usuário
            $stmtInv = $this->pdo->prepare(
                "UPDATE recuperacao_senha_tokens 
                 SET invalidado_em = NOW() 
                 WHERE usuario_id = ? AND usado_em IS NULL AND invalidado_em IS NULL"
            );
            $stmtInv->execute([$usuarioId]);

            // Persiste exclusivamente o hash SHA-256 com expiração curta (15 minutos)
            $stmtIns = $this->pdo->prepare(
                "INSERT INTO recuperacao_senha_tokens (usuario_id, token_hash, canal, ip_solicitante, expira_em)
                 VALUES (?, ?, 'email', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
            );
            $stmtIns->execute([
                $usuarioId,
                $tokenHash,
                $ipCliente !== null ? substr((string) $ipCliente, 0, 45) : null
            ]);

            $this->registrarAuditoriaSeguranca(
                $usuarioId,
                'solicitar_recuperacao',
                $ipCliente,
                'Token temporário emitido para canal verificado (hash_prefix=' . substr($tokenHash, 0, 8) . ')'
            );

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }
        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // Envio pelo canal verificado do titular (sem jamais expor o token na resposta HTTP ou em logs)
        if (is_callable(self::$canalNotificacaoCallback)) {
            call_user_func(self::$canalNotificacaoCallback, $user['email'], $rawToken, $user);
        } else {
            $assunto = 'Recuperação de Senha - Clube Faz Bem';
            $corpo = "Olá {$user['nome']},\n\nSeu código temporário de recuperação de senha é: {$rawToken}\nEle expira em 15 minutos e possui uso único.\n";
            $headers = "From: nao-responda@clubefazbem.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($user['email'], $assunto, $corpo, $headers);
        }

        return ['success' => true, 'message' => $mensagemUniforme];
    }

    /**
     * Consome um token válido de uso único, redefine a senha, invalida tokens e sessões anteriores e audita a operação.
     */
    public function redefinirSenhaComToken(string $rawToken, string $novaSenha, ?string $email = null, ?string $ip = null): bool {
        $tokenLimpo = trim($rawToken);
        $ipCliente = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        if ($tokenLimpo === '' || strlen($tokenLimpo) < 16) {
            $this->registrarAuditoriaSeguranca(null, 'redefinir_senha_falha', $ipCliente, 'Formato de token inválido');
            throw new Exception("Token de recuperação inválido ou expirado.", 400);
        }

        if (strlen($novaSenha) < 8) {
            throw new Exception("A senha deve ter pelo menos 8 caracteres.", 400);
        }

        $tokenHash = hash('sha256', $tokenLimpo);

        $iniciadoAqui = !$this->pdo->inTransaction();
        if ($iniciadoAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.id, t.usuario_id, t.usado_em, t.invalidado_em, (t.expira_em > NOW()) AS vigente, u.email
                 FROM recuperacao_senha_tokens t
                 JOIN usuarios u ON t.usuario_id = u.id
                 WHERE t.token_hash = ?
                 FOR UPDATE"
            );
            $stmt->execute([$tokenHash]);
            $registro = $stmt->fetch();

            $emailConfere = ($email === null || trim($email) === '')
                || ($registro && strcasecmp((string) $registro['email'], trim($email)) === 0);

            if (
                !$registro
                || !$emailConfere
                || $registro['usado_em'] !== null
                || $registro['invalidado_em'] !== null
                || (int) $registro['vigente'] !== 1
            ) {
                $uidFalha = $registro ? (int) $registro['usuario_id'] : null;
                $this->registrarAuditoriaSeguranca(
                    $uidFalha,
                    'redefinir_senha_falha',
                    $ipCliente,
                    'Tentativa com token inexistente, expirado, invalidado ou já utilizado'
                );
                if ($iniciadoAqui) {
                    $this->pdo->commit();
                }
                throw new Exception("Token de recuperação inválido ou expirado.", 400);
            }

            $tokenId = (int) $registro['id'];
            $usuarioId = (int) $registro['usuario_id'];
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

            // Marca este token como usado (uso único) e invalida quaisquer outros tokens pendentes do usuário
            $this->pdo->prepare("UPDATE recuperacao_senha_tokens SET usado_em = NOW() WHERE id = ?")
                ->execute([$tokenId]);
            $this->pdo->prepare(
                "UPDATE recuperacao_senha_tokens 
                 SET invalidado_em = NOW() 
                 WHERE usuario_id = ? AND id != ? AND usado_em IS NULL AND invalidado_em IS NULL"
            )->execute([$usuarioId, $tokenId]);

            // Atualiza senha e incrementa session_version para invalidar todas as sessões anteriores
            $this->pdo->prepare("UPDATE usuarios SET senha = ?, session_version = session_version + 1 WHERE id = ?")
                ->execute([$senhaHash, $usuarioId]);

            $this->registrarAuditoriaSeguranca(
                $usuarioId,
                'redefinir_senha_sucesso',
                $ipCliente,
                'Senha redefinida via token de uso único; sessões e tokens anteriores invalidados'
            );

            if ($iniciadoAqui) {
                $this->pdo->commit();
            }

            if (!empty($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] === $usuarioId) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_regenerate_id(true);
                }
            }

            return true;
        } catch (Exception $e) {
            if ($iniciadoAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    
    public function buscarPorEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(); 
    }

    
    public function cadastrarCliente($nome, $email, $senhaCriptografada, $telefone, $endereco, $referencia, $frequencia, $latitude = null, $longitude = null, $cpf = null) {
        try {
            
            $this->pdo->beginTransaction();

            
            $sqlUser = "INSERT INTO usuarios (nome, email, senha, telefone, cpf, endereco, ponto_referencia, tipo_usuario, aceite_privacidade_em) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'cliente', NOW())";
            $stmtUser = $this->pdo->prepare($sqlUser);
            $stmtUser->execute([$nome, $email, $senhaCriptografada, $telefone, $cpf, $endereco, $referencia]);
            
            
            $usuarioId = $this->pdo->lastInsertId();

            if ($latitude !== null && $longitude !== null) {
                $sqlEndereco = "INSERT INTO enderecos (usuario_id, logradouro, ponto_referencia, is_principal, latitude, longitude) 
                                VALUES (?, ?, ?, 1, ?, ?)";
                $stmtEndereco = $this->pdo->prepare($sqlEndereco);
                $stmtEndereco->execute([$usuarioId, $endereco, $referencia, $latitude, $longitude]);
            }

            
            if (!empty($frequencia)) {
                $valor_mensal = ($frequencia === 'Quinzenal') ? 50.00 : 100.00;
                $sqlAssinatura = "INSERT INTO assinaturas (usuario_id, frequencia, valor_mensal, status) VALUES (?, ?, ?, 'Cancelada')";
                $stmtAssinatura = $this->pdo->prepare($sqlAssinatura);
                $stmtAssinatura->execute([$usuarioId, $frequencia, $valor_mensal]);

                $mes_referencia = date('Y-m');
                $sqlFatura = "INSERT INTO faturas_mensais (usuario_id, mes_referencia, valor_mensalidade, valor_extras, valor_desconto_creditos, valor_total, status) 
                              VALUES (?, ?, ?, 0.00, 0.00, ?, 'Pendente')";
                $stmtFatura = $this->pdo->prepare($sqlFatura);
                $stmtFatura->execute([$usuarioId, $mes_referencia, $valor_mensal, $valor_mensal]);
            }

            
            $this->pdo->commit();
            return $usuarioId;

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
        $sql = "SELECT id, nome, email, telefone, cpf, endereco, ponto_referencia 
                FROM usuarios WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function atualizarPerfil($id, $nome, $telefone, $cpf, $endereco, $referencia, $senhaHash = null) {
        if ($senhaHash) {
            // Se o cliente digitou uma senha nova, atualiza tudo, invalida sessões/tokens anteriores e audita
            $sql = "UPDATE usuarios SET nome = ?, telefone = ?, cpf = ?, endereco = ?, ponto_referencia = ?, senha = ?, session_version = session_version + 1 WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([$nome, $telefone, $cpf, $endereco, $referencia, $senhaHash, $id]);

            $this->pdo->prepare(
                "UPDATE recuperacao_senha_tokens SET invalidado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL AND invalidado_em IS NULL"
            )->execute([(int) $id]);

            $this->registrarAuditoriaSeguranca((int) $id, 'alterar_senha_perfil', null, 'Senha alterada pelo titular no perfil; sessões anteriores invalidadas');

            if (!empty($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] === (int) $id) {
                $stmtVer = $this->pdo->prepare("SELECT session_version FROM usuarios WHERE id = ?");
                $stmtVer->execute([(int) $id]);
                $_SESSION['session_version'] = (int) ($stmtVer->fetchColumn() ?: 1);
            }
            return $ok;
        } else {
            // Se a senha veio em branco, atualiza só os dados de contato
            $sql = "UPDATE usuarios SET nome = ?, telefone = ?, cpf = ?, endereco = ?, ponto_referencia = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$nome, $telefone, $cpf, $endereco, $referencia, $id]);
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