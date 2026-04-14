async function handleLogin() {
      const email = document.getElementById('email').value;
      const senha = document.getElementById('senha').value;

      if (!email || !senha) {
        alert('Por favor, preencha e-mail e senha.');
        return;
      }

      try {
        const response = await fetch('api_login_v2.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, senha })
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