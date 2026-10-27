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
          const redirect = result.redirect || 'index.html';
          // Previne open redirect validando se a URL é relativa ou do próprio domínio
          if ((redirect.startsWith('/') && !redirect.startsWith('//')) || redirect.startsWith(window.location.origin)) {
              window.location.href = redirect;
          } else {
              window.location.href = 'index.html';
          }
        } else {
          alert(result.message); 
        }
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor.');
      }
    }

    async function solicitarTokenRecuperacao() {
      const email = document.getElementById('recEmail').value.trim();
      if (!email) {
        alert('Informe o seu e-mail cadastrado.');
        return;
      }

      try {
        const response = await fetch('api_recuperar_senha.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'solicitar', email })
        });
        const result = await response.json();
        alert(result.message || 'Solicitação processada.');
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor.');
      }
    }

    async function recuperarSenha() {
      const email = document.getElementById('recEmail').value.trim();
      const tokenEl = document.getElementById('recToken');
      const token = tokenEl ? tokenEl.value.trim() : '';
      const novaSenha = document.getElementById('recNovaSenha').value.trim();

      if (!token || !novaSenha) {
        alert('Por favor, informe o código de recuperação recebido e a nova senha.');
        return;
      }

      if (novaSenha.length < 8) {
        alert('A nova senha deve ter pelo menos 8 caracteres.');
        return;
      }

      try {
        const response = await fetch('api_recuperar_senha.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'redefinir', email, token, nova_senha: novaSenha })
        });

        const result = await response.json();

        if (result.success) {
          alert('Senha alterada com sucesso! Você já pode fazer login com a nova senha.');
          document.getElementById('modalRecuperarSenha').style.display = 'none';
          document.getElementById('senha').value = '';
          document.getElementById('recEmail').value = '';
          if (tokenEl) tokenEl.value = '';
          document.getElementById('recNovaSenha').value = '';
        } else {
          alert('Erro: ' + result.message);
        }
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor.');
      }
    }