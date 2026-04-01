<?php
// Caminho: faz_bem_v2/setup_admin_v2.php
require_once __DIR__ . '/app/Models/Usuario.php';

// === CHAVE DE SEGURANÇA ===
$CHAVE_SECRETA = 'MeuSetup2026'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chave_digitada = $_POST['chave'] ?? '';

    if ($chave_digitada !== $CHAVE_SECRETA) {
        die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2 style='color:#dc2626;'>❌ Chave incorreta! Acesso negado.</h2>
                <a href='setup_admin_v2.php' style='color:#2b8a3e;'>Tentar novamente</a>
             </div>");
    }

    // --- DADOS DO ADMIN ---
    $nome_admin = 'Administrador';
    $email_admin = 'admin@fazbem.com';
    $senha_pura = '123456'; 

    // Criptografa a senha antes de enviar
    $senhaHash = password_hash($senha_pura, PASSWORD_DEFAULT);

    try {
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        
        // Usa a Orientação a Objetos para salvar o Admin
        $usuarioModel = new Usuario();
        $resultado = $usuarioModel->salvarAdmin($nome_admin, $email_admin, $senhaHash);

        if ($resultado === 'atualizado') {
            echo "<h2 style='color:#16a34a;'>✅ Administrador atualizado!</h2>";
            echo "<p>A senha de <strong>$email_admin</strong> foi convertida para Hash na versão V2.</p>";
        } else {
            echo "<h2 style='color:#16a34a;'>✅ Administrador criado!</h2>";
            echo "<p>O utilizador <strong>$email_admin</strong> foi criado com sucesso no novo formato.</p>";
        }

        echo "<p style='margin-top:20px;'><a href='login.html' style='padding:10px 20px; background:#2b8a3e; color:white; text-decoration:none; border-radius:5px;'>Ir para o Login</a></p>";
        echo "<p style='color:#dc2626; margin-top:30px;'>⚠️ <strong>Aviso:</strong> Apague este ficheiro (<code>setup_admin_v2.php</code>) após o uso por questões de segurança.</p>";
        echo "</div>";
        
        exit;
        
    } catch (Exception $e) {
        die("<h2 style='color:red;'>❌ Erro na base de dados:</h2><p>" . $e->getMessage() . "</p>");
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin V2</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #1f2937; }
        .box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 400px; }
        input { padding: 12px; margin: 15px 0; width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px; }
        button { background: #2b8a3e; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 6px; width: 100%; font-size: 16px; font-weight: bold; }
        button:hover { background: #15803d; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-top:0; color:#2b8a3e;">🔒 Criar Admin V2</h2>
        <p style="color:#6b7280; font-size:14px;">Digite a chave de segurança para gerar o acesso na nova base de dados.</p>
        <form method="POST">
            <input type="password" name="chave" placeholder="Chave de segurança..." required>
            <button type="submit">Configurar Acesso</button>
        </form>
    </div>
</body>
</html>