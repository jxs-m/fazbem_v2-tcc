document.getElementById('formCadastro').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const btn = document.getElementById('btnCadastrar');
      const originalText = btn.innerText;
      
      
      btn.innerText = "Criando conta...";
      btn.disabled = true;

      
      const dados = {
        nome: document.getElementById('nome').value,
        email: document.getElementById('email').value,
        senha: document.getElementById('senha').value,
        cpf: document.getElementById('cpf').value,
        telefone: document.getElementById('telefone').value,
        endereco: document.getElementById('endereco').value,
        referencia: document.getElementById('referencia').value,
        frequencia: document.getElementById('frequencia').value
      };

      try {
        
        const response = await fetch('api_cadastro_v2.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(dados)
        });

        
        const text = await response.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (err) {
            console.error("Resposta inválida do servidor:", text);
            throw new Error("Erro técnico no servidor.");
        }

        if (json.success) {
          alert('✅ Cadastro realizado com sucesso!\n\nVocê será redirecionado para o login.');
          window.location.href = 'login.html';
        } else {
          alert('❌ Erro: ' + json.message);
        }

      } catch (error) {
        alert('Erro de conexão ao cadastrar. Tente novamente.');
        console.error(error);
      } finally {
       
        btn.innerText = originalText;
        btn.disabled = false;
      }
    });