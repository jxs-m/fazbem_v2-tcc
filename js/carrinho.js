let carrinho = JSON.parse(localStorage.getItem('fazbem_carrinho')) || [];

    function renderizarCarrinho() {
      const container = document.getElementById('lista-itens');
      const totalDisplay = document.getElementById('total-display');
      const btn = document.getElementById('btn-finalizar');
      const pagBox = document.getElementById('pagamento-box');

      if (carrinho.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999">Sua cesta está vazia.<br><a href="catalogo.html" style="color:var(--green-1)">Voltar ao catálogo</a></div>';
        totalDisplay.innerText = 'R$ 0,00';
        btn.disabled = true;
        pagBox.style.display = 'none';
        return;
      }

      pagBox.style.display = 'block';
      let html = '';
      let total = 0;

      carrinho.forEach((item, index) => {
        let subtotal = item.preco * item.quantidade;
        total += subtotal;

        html += `
          <div class="cart-item">
            <div class="item-info">
              <h4>${escapeHTML(item.nome)}</h4>
              <span>R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</span>
            </div>
            <div class="qtd-ctrl">
              <button class="btn-circle" onclick="alterarQtd(${index}, -1)">-</button>
              <span style="font-weight:600; min-width:20px; text-align:center">${item.quantidade}</span>
              <button class="btn-circle" onclick="alterarQtd(${index}, 1)">+</button>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
      totalDisplay.innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
      btn.disabled = false;
    }

    function alterarQtd(index, delta) {
      carrinho[index].quantidade += delta;

      if (carrinho[index].quantidade <= 0) {
        if (confirm('Remover ' + carrinho[index].nome + ' da cesta?')) {
          carrinho.splice(index, 1);
        } else {
          carrinho[index].quantidade = 1;
        }
      }

      localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinho));
      renderizarCarrinho();
    }

    async function finalizarPedido() {

      const pagamento = document.querySelector('input[name="pagamento"]:checked').value;

      if (!confirm(`Confirmar pedido no valor total?\nPagamento via: ${pagamento}`)) return;

      const btn = document.getElementById('btn-finalizar');
      btn.innerText = "Processando...";
      btn.disabled = true;

      const totalCalculado = carrinho.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);

      try {
        const res = await fetch('api_checkout_v2.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            itens: carrinho,
            total: totalCalculado,
            pagamento: pagamento
          })
        });


        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { json = { success: false, message: "Erro no servidor: " + text }; }

        if (json.success) {
          alert('✅ Pedido Confirmado!\n\nSeus itens já estão reservados e o estoque atualizado.');
          localStorage.removeItem('fazbem_carrinho'); // Limpa carrinho
          window.location.href = 'catalogo.html'; // Volta para loja
        } else {
          alert('❌ ' + json.message);
          if (json.message.includes('login')) window.location.href = 'login.html';
        }

      } catch (e) {
        alert('Erro de conexão ao finalizar.');
        console.error(e);
      } finally {
        btn.innerText = "Finalizar Pedido";
        btn.disabled = false;
      }
    }


    renderizarCarrinho();