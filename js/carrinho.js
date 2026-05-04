let carrinho = JSON.parse(localStorage.getItem('fazbem_carrinho')) || [];

    function renderizarCarrinho() {
      const container = document.getElementById('lista-itens');
      const totalDisplay = document.getElementById('total-display');
      const btn = document.getElementById('btn-finalizar');
      const pagBox = document.getElementById('pagamento-box');

      const pesoDisplay = document.getElementById('peso-display');

      if (carrinho.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999">Sua cesta está vazia.<br><a href="catalogo.html" style="color:var(--green-1)">Voltar ao catálogo</a></div>';
        totalDisplay.innerText = 'R$ 0,00';
        pesoDisplay.innerText = '';
        btn.disabled = true;
        pagBox.style.display = 'none';
        return;
      }

      pagBox.style.display = 'block';
      let html = '';
      let total = 0;
      let totalPesoG = 0;

      carrinho.forEach((item, index) => {
        let subtotal = item.preco * item.quantidade;
        total += subtotal;
        totalPesoG += (item.peso_estimado_g || 0) * item.quantidade;

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
      
      if (totalPesoG > 0) {
        pesoDisplay.innerText = `Peso estimado: ${(totalPesoG / 1000).toFixed(2)} kg`;
      } else {
        pesoDisplay.innerText = '';
      }

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


    async function carregarResumoFiscal() {
        const nfBox = document.getElementById('nota-fiscal-box');
        const nfContent = document.getElementById('nota-fiscal-content');

        try {
            // Buscamos dados do perfil (para ver preferências) e config (para o kit padrão)
            const [resPerfil, resConfig, resAssinatura] = await Promise.all([
                fetch('api_perfil_v2.php'),
                fetch('api_config.php'),
                fetch('api_minha_assinatura_v2.php')
            ]);

            const jsonPerfil = await resPerfil.json();
            const jsonConfig = await resConfig.json();
            const jsonAssinatura = await resAssinatura.json();

            // Se não estiver logado ou não tiver assinatura ativa, só mostra os adicionais
            const temAssinatura = jsonAssinatura.success && jsonAssinatura.data && jsonAssinatura.data.status === 'Ativa';

            let htmlNota = '';

            if (temAssinatura) {
                const kitBase = (jsonConfig.success && jsonConfig.data.kit_semana) ? jsonConfig.data.kit_semana : 'Kit Padrão (Verifique com a administração)';
                htmlNota += `<div style="margin-bottom:12px;">
                    <strong style="color:#166534;">📦 Kit Base da Assinatura:</strong><br>
                    <span style="color:#4b5563;">${escapeHTML(kitBase)}</span>
                </div>`;

                if (jsonPerfil.success && jsonPerfil.preferencias && jsonPerfil.preferencias.length > 0) {
                    htmlNota += `<div style="margin-bottom:12px;">
                        <strong style="color:#b45309;">🔄 Suas Exceções / Trocas:</strong><ul style="margin:4px 0 0 0; padding-left:20px; color:#4b5563;">`;
                    jsonPerfil.preferencias.forEach(p => {
                        let tag = p.tipo === 'Troca Pontual' ? '<span style="background:#fef3c7; border:1px solid #fde68a; font-size:10px; padding:2px 4px; border-radius:4px; font-weight:bold; color:#b45309;">SÓ ESSA SEMANA</span>' : '';
                        htmlNota += `<li>${tag} ${escapeHTML(p.descricao)}</li>`;
                    });
                    htmlNota += `</ul></div>`;
                }
            } else {
                htmlNota += `<div style="margin-bottom:12px; color:#9ca3af; font-style:italic;">Você não possui uma assinatura ativa para receber o Kit Base.</div>`;
            }

            if (carrinho.length > 0) {
                htmlNota += `<div>
                    <strong style="color:#1d4ed8;">🛒 Adicionais (Cobrados à parte):</strong><ul style="margin:4px 0 0 0; padding-left:20px; color:#4b5563;">`;
                carrinho.forEach(item => {
                    htmlNota += `<li>${item.quantidade}x ${escapeHTML(item.nome)}</li>`;
                });
                htmlNota += `</ul></div>`;
            } else {
                htmlNota += `<div style="color:#9ca3af; font-style:italic;">Nenhum item adicional no carrinho.</div>`;
            }

            nfContent.innerHTML = htmlNota;
            nfBox.style.display = 'block';

        } catch (e) {
            nfContent.innerHTML = '<span style="color:#dc2626">Erro ao carregar o resumo completo do pedido.</span>';
            nfBox.style.display = 'block';
        }
    }

    renderizarCarrinho();
    carregarResumoFiscal();