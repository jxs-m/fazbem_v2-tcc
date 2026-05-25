let carrinho = JSON.parse(localStorage.getItem('fazbem_carrinho')) || [];

    function getBaseGrams(unidadeStr) {
      let u = (unidadeStr || '').toLowerCase();
      if (u.includes('kg')) return 1000;
      if (u.includes('g') && !u.includes('kg')) {
         let num = parseInt(u);
         if (!isNaN(num) && num > 0) return num;
      }
      return null;
    }

    function renderizarCarrinho() {
      const container = document.getElementById('lista-itens');
      const totalDisplay = document.getElementById('total-display');
      const btn = document.getElementById('btn-finalizar');

      const pesoDisplay = document.getElementById('peso-display');

      if (carrinho.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999">Sua cesta está vazia.<br><a href="catalogo.html" style="color:var(--green-1)">Voltar ao catálogo</a></div>';
        totalDisplay.innerText = 'R$ 0,00';
        pesoDisplay.innerText = '';
        btn.disabled = true;
        return;
      }

      let html = '';
      let total = 0;
      let totalPesoG = 0;

      carrinho.forEach((item, index) => {
        // Compatibilidade com o formato antigo do carrinho
        let isNovoModelo = (item.preco_estimado_calculado !== undefined && item.preco_estimado_calculado !== null);
        let subtotal = isNovoModelo ? item.preco_estimado_calculado : (parseFloat(item.preco_base || item.preco || 0) * (item.quantidade_calculada || item.quantidade || 0));
        if (isNaN(subtotal) || subtotal === null) subtotal = 0;
        let safeQtd = item.input_qtd || item.quantidade || 0;
        let qtyLabel = isNovoModelo ? (item.tipo_compra === 'Unidade' ? `${safeQtd}x` : `${safeQtd}g`) : `${item.quantidade}x`;
        
        total += subtotal;
        if(isNovoModelo) {
            totalPesoG += item.gramas_calculadas || 0;
        } else {
            totalPesoG += (item.peso_estimado_g || 0) * item.quantidade;
        }

        html += `
          <div class="cart-item">
            <div class="item-info">
              <h4>${escapeHTML(item.nome)}</h4>
              <span>R$ ${parseFloat(subtotal).toFixed(2).replace('.', ',')}</span>
            </div>
            <div class="qtd-ctrl">
              <button class="btn-circle" onclick="alterarQtd(${index}, -1)">-</button>
              <span style="font-weight:600; min-width:30px; text-align:center">${qtyLabel}</span>
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
      let item = carrinho[index];
      let isNovoModelo = (item.preco_estimado_calculado !== undefined);

      if (isNovoModelo) {
          let d = (item.tipo_compra === 'Unidade') ? delta : (delta * 10); // +1 ou +10g
          item.input_qtd += d;
          
          if (item.input_qtd <= 0) {
              if (confirm('Remover ' + item.nome + ' da cesta?')) {
                  carrinho.splice(index, 1);
              } else {
                  item.input_qtd = (item.tipo_compra === 'Unidade') ? 1 : 100;
              }
          }

          if (item.input_qtd > 0) {
              // Recalcula
              let baseGrams = getBaseGrams(item.unidade);
              let multiplier = 0;
              let requestedGrams = 0;

              if (item.tipo_compra === 'Unidade') {
                  requestedGrams = item.input_qtd * item.peso_estimado_g;
                  multiplier = (baseGrams !== null) ? (requestedGrams / baseGrams) : item.input_qtd;
              } else {
                  requestedGrams = item.input_qtd;
                  multiplier = (baseGrams !== null) ? (requestedGrams / baseGrams) : item.input_qtd;
              }

              item.quantidade_calculada = multiplier;
              item.gramas_calculadas = requestedGrams;
              item.preco_estimado_calculado = multiplier * item.preco_base;
          }
      } else {
          // Antigo
          item.quantidade += delta;
          if (item.quantidade <= 0) {
              if (confirm('Remover ' + item.nome + ' da cesta?')) {
                  carrinho.splice(index, 1);
              } else {
                  item.quantidade = 1;
              }
          }
      }

      localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinho));
      renderizarCarrinho();
    }

    async function finalizarPedido() {

      if (!confirm(`Confirmar pedido no valor total?`)) return;

      const btn = document.getElementById('btn-finalizar');
      btn.innerText = "Processando...";
      btn.disabled = true;

      const totalCalculado = carrinho.reduce((acc, item) => acc + (item.preco_estimado_calculado !== undefined ? item.preco_estimado_calculado : (item.preco * item.quantidade)), 0);

      try {
        const res = await fetch('api_checkout_v2.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            itens: carrinho,
            total: totalCalculado,
            pagamento: 'Fatura Mensal'
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
             const [resPerfil, resConfig, resAssinatura] = await Promise.all([
                fetch('api_perfil_v2.php'),
                fetch('api_config.php'),
                fetch('api_minha_assinatura_v2.php')
            ]);

            const jsonPerfil = await resPerfil.json();
            const jsonConfig = await resConfig.json();
            const jsonAssinatura = await resAssinatura.json();

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
                    let safeQtd = item.input_qtd || item.quantidade || 0;
                    let qtyLabel = (item.preco_estimado_calculado !== undefined) ? (item.tipo_compra === 'Unidade' ? `${safeQtd}x` : `${safeQtd}g de`) : `${item.quantidade}x`;
                    htmlNota += `<li>${qtyLabel} ${escapeHTML(item.nome)}</li>`;
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