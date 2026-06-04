async function handleLogin() {
      const email = document.getElementById('email').value;
      const senha = document.getElementById('senha').value;

      if (!email || !senha) {
        alert('Por favor, preencha e-mail e senha.');
        return;
      }

      try {
        const params = new URLSearchParams();
        params.append('email', email);
        params.append('senha', senha);

        const response = await fetch('api_login_v2.php', {
          method: 'POST',
          body: params
        });

        const result = await response.json();

        if (result.success) {
          
          window.location.href = result.redirect; 
        } else {
          alert(result.message); 
        }
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor.');
      }
    }

    async function recuperarSenha() {
      const email = document.getElementById('recEmail').value.trim();
      const telefone = document.getElementById('recTelefone').value.trim().replace(/\D/g, '');
      const novaSenha = document.getElementById('recNovaSenha').value.trim();

      if (!email || !telefone || !novaSenha) {
        alert('Por favor, preencha todos os campos.');
        return;
      }

      if (novaSenha.length < 6) {
        alert('A nova senha deve ter pelo menos 6 caracteres.');
        return;
      }

      try {
        const response = await fetch('api_recuperar_senha.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, telefone, nova_senha: novaSenha })
        });

        const result = await response.json();

        if (result.success) {
          alert('Senha alterada com sucesso! Você já pode fazer login com a nova senha.');
          document.getElementById('modalRecuperarSenha').style.display = 'none';
          document.getElementById('senha').value = '';
          document.getElementById('recEmail').value = '';
          document.getElementById('recTelefone').value = '';
          document.getElementById('recNovaSenha').value = '';
        } else {
          alert('Erro: ' + result.message);
        }
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor.');
      }
    }