let carrinhoDados = JSON.parse(localStorage.getItem('fazbem_carrinho')) || [];
    atualizarContador();

    const icones = {
      'Temporários': '⏳',
      'Legumes': '🍅',
      'Verduras': '🥬',
      'Frutas': '🍊',
      'Processados': '🥕',
      'Outros': '📦'
    };

    const descricoes = {
      'Temporários': 'Produtos sazonais e de época, disponíveis por tempo limitado!',
      'Legumes': 'Origem: frutos comestíveis (Abóbora, Tomate, etc.)',
      'Verduras': 'Origem: folhas, talos ou brotos comestíveis',
      'Frutas': 'Frutas frescas e da estação',
      'Processados': 'Itens higienizados, picados e prontos para consumo'
    };

    document.addEventListener('DOMContentLoaded', carregarCatalogo);

    async function carregarCatalogo() {
      const container = document.getElementById('catalogo-container');
      try {
        const response = await fetch('api_catalogo_v2.php');
        if (!response.ok) throw new Error('Falha na requisição');

        const json = await response.json();

        if (json.success) {
          container.innerHTML = '';
          const grupos = agruparPorCategoria(json.data);
          const ordem = ['Temporários', 'Legumes', 'Verduras', 'Frutas', 'Processados', 'Outros'];

          if (json.isOpen === false) {
             const banner = document.createElement('div');
             banner.style.backgroundColor = '#fecaca';
             banner.style.color = '#991b1b';
             banner.style.padding = '15px';
             banner.style.borderRadius = '8px';
             banner.style.textAlign = 'center';
             banner.style.marginBottom = '20px';
             banner.innerHTML = '<h3>⚠️ VENDAS ENCERRADAS PARA ESTE CICLO</h3><p>O limite logístico foi atingido ou o horário de compras fechou. Retornaremos no próximo ciclo!</p>';
             container.appendChild(banner);
          }

          ordem.forEach(cat => {
            if (grupos[cat] && grupos[cat].length > 0) {
              renderizarSecao(container, cat, grupos[cat], json.isOpen);
            }
          });
        } else {
          container.innerHTML = '<p style="text-align:center; color:#dc2626">Erro ao carregar o catálogo.</p>';
        }
      } catch (error) {
        console.error(error);
        container.innerHTML = '<p style="text-align:center; color:#dc2626">Erro de conexão. Verifique se está logado.</p>';
      }
    }

    function agruparPorCategoria(produtos) {
      return produtos.reduce((acc, produto) => {
        const cat = (parseInt(produto.temporario) === 1) ? 'Temporários' : produto.categoria;
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(produto);
        return acc;
      }, {});
    }

    function renderizarSecao(container, categoria, produtos, isOpen) {
      const section = document.createElement('section');
      const iconeCat = icones[categoria] || '📦';
      const desc = descricoes[categoria] || '';

      let htmlProdutos = '';

      produtos.forEach(p => {
        const precoFormatado = parseFloat(p.preco).toFixed(2).replace('.', ',');


        const imagemDisplay = p.imagem_url
          ? `<img src="${escapeHTML(p.imagem_url)}" alt="${escapeHTML(p.nome)}" class="imagem-produto">`
          : `<span class="prod-icon">${iconeCat}</span>`;
        const durationDisplay = p.dias_restantes 
          ? `<div style="margin-top: 4px; margin-bottom: 4px;"><span class="prod-duration" style="background: #fffbeb; color: #b45309; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; border: 1px solid #fde68a; display: inline-block;">⏳ Restam ${p.dias_restantes} ${p.dias_restantes === 1 ? 'dia' : 'dias'}</span></div>`
          : '';
        htmlProdutos += `
          <div class="card">
            ${imagemDisplay}
            <div class="prod-name">${escapeHTML(p.nome)}</div>
            ${durationDisplay}
            <div class="prod-price">R$ ${precoFormatado} <span class="prod-unit">/ ${escapeHTML(p.unidade)}</span></div>
            <div class="actions">
              ${isOpen === false ? '<p style="color: #991b1b; font-weight: bold; width: 100%; text-align: center; margin: 0;">Esgotado / Fechado</p>' : `<button class="btn-add" onclick='prepararAdicaoModal(${JSON.stringify(p).replace(/'/g, "&#39;")})'>
                + Adicionar
              </button>
              <button class="btn-swap" onclick="abrirTroca('${escapeHTML(p.nome.replace(/'/g, "\\'"))}')">
                ⇄ Trocar Item
              </button>`}
            </div>
          </div>
        `;
      });

      section.innerHTML = `
        <h2>${iconeCat} ${categoria}</h2>
        ${desc ? `<div class="cat-desc">${desc}</div>` : ''}
        <div class="product-grid">
          ${htmlProdutos}
        </div>
      `;

      container.appendChild(section);
    }

    function adicionarCarrinho(nome, id, preco, peso) {
      let itemExistente = carrinhoDados.find(i => i.id === id);
      if (itemExistente) {
        itemExistente.quantidade++;
      } else {
        carrinhoDados.push({
          id: id,
          nome: nome,
          preco: parseFloat(preco),
          peso_estimado_g: peso || 0,
          quantidade: 1
        });
      }
      localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinhoDados));
      atualizarContador();
      alert(`"${nome}" foi adicionado à sua cesta!`);
    }

    function atualizarContador() {
      let totalItens = carrinhoDados.reduce((acc, item) => {
          let q = 1;
          if (item.preco_estimado_calculado !== undefined) {
              // Se for unidade, soma a quantidade. Se for peso (ex: 500g), conta como 1 pacote/item na cesta.
              q = (item.tipo_compra === 'Unidade') ? (item.input_qtd || 1) : 1;
          } else {
              q = item.quantidade || 1;
          }
          return acc + q;
      }, 0);
      document.getElementById('cart-count').innerText = totalItens;
    }

    function abrirTroca(nome) {
      const opcao = prompt(
        `Opções de troca para ${nome}:\n\n` +
        `Digite 1 para: Trocar apenas nesta semana (Pontual)\n` +
        `Digite 2 para: Trocar sempre (Regra Fixa)`
      );
      if (opcao === '1') alert(`Troca pontual de ${nome} registrada.`);
      else if (opcao === '2') {
        alert(`Redirecionando para seu perfil para salvar a preferência fixa...`);
        window.location.href = 'perfil.html';
      }
    }

    function repetirPedido() {
      if (confirm('Carregar itens da última semana? Isso substituirá seu carrinho atual.')) {
        carrinhoDados = [
          { id: 1, nome: 'Abóbora', preco: 6.00, quantidade: 1 },
          { id: 3, nome: 'Alface Crespa', preco: 3.50, quantidade: 2 },
          { id: 7, nome: 'Kit Sopa', preco: 9.90, quantidade: 1 }
        ];
        localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinhoDados));
        atualizarContador();
        alert('Itens carregados com sucesso!');
      }
    }

    let prodAdicaoAtual = null;
    let tipoCompraAtual = 'Unidade';

    function setTipoCompraModal(tipo) {
        tipoCompraAtual = tipo;
        let p = prodAdicaoAtual;
        let inputQtd = document.getElementById('modalAddQtd');
        let hint = document.getElementById('modalAddHint');

        document.getElementById('btnTipoUnidade').style.background = (tipo === 'Unidade') ? '#dcfce7' : '#f3f4f6';
        document.getElementById('btnTipoUnidade').style.color = (tipo === 'Unidade') ? '#166534' : '#4b5563';
        document.getElementById('btnTipoUnidade').style.border = (tipo === 'Unidade') ? '1px solid #166534' : '1px solid #d1d5db';

        document.getElementById('btnTipoPeso').style.background = (tipo === 'Peso') ? '#dcfce7' : '#f3f4f6';
        document.getElementById('btnTipoPeso').style.color = (tipo === 'Peso') ? '#166534' : '#4b5563';
        document.getElementById('btnTipoPeso').style.border = (tipo === 'Peso') ? '1px solid #166534' : '1px solid #d1d5db';

        if (tipo === 'Peso') {
            inputQtd.value = 100;
            inputQtd.step = 10;
            hint.innerText = 'Ex: 230 para 230 gramas';
            document.getElementById('modalAddLabel').innerText = 'Quantas gramas você precisa?';
        } else {
            inputQtd.value = 1;
            inputQtd.step = 1;
            hint.innerText = 'Ex: 10 unidades';
            document.getElementById('modalAddLabel').innerText = 'Quantas unidades você precisa?';
        }
        calcularEstimativaModal();
    }

    function getBaseGrams(unidadeStr) {
      let u = (unidadeStr || '').toLowerCase();
      if (u.includes('kg')) return 1000;
      if (u.includes('g') && !u.includes('kg')) {
         let num = parseInt(u);
         if (!isNaN(num) && num > 0) return num;
      }
      return null;
    }

    function prepararAdicaoModal(p) {
        prodAdicaoAtual = p;
        document.getElementById('modalAdicionarProduto').style.display = 'flex';
        document.getElementById('modalAddNome').innerText = p.nome;
        document.getElementById('modalAddPrecoBase').innerText = `R$ ${parseFloat(p.preco).toFixed(2).replace('.', ',')} / ${p.unidade}`;
        
        let containerTipo = document.getElementById('modalAddTipoContainer');
        let inputQtd = document.getElementById('modalAddQtd');
        let hint = document.getElementById('modalAddHint');
        
        if (p.tipo_venda === 'Fracionado') {
            // Vendido de forma fracionada (cebola picada, etc). Força "Por Peso".
            containerTipo.style.display = 'none';
            setTipoCompraModal('Peso');
        } else {
            // Vendido de forma inteira (bananas, alface, etc). Força "Por Unidade".
            containerTipo.style.display = 'none';
            setTipoCompraModal('Unidade');
        }

        calcularEstimativaModal();
    }

    function calcularEstimativaModal() {
        if (!prodAdicaoAtual) return;
        let p = prodAdicaoAtual;
        let qtd = parseFloat(document.getElementById('modalAddQtd').value) || 0;
        let baseGrams = getBaseGrams(p.unidade);
        let pesoEstimadoItem = parseFloat(p.peso_estimado_g) || 0;
        
        let multiplier = 0;
        let requestedGrams = 0;

        if (tipoCompraAtual === 'Unidade') {
            if (baseGrams !== null) {
                // Vendido a peso, mas cliente pediu unidades
                requestedGrams = qtd * pesoEstimadoItem;
                multiplier = requestedGrams / baseGrams;
            } else {
                // Vendido a unidade (alface)
                requestedGrams = qtd * pesoEstimadoItem;
                multiplier = qtd;
            }
        } else {
            // Comprando em gramas direto
            requestedGrams = qtd;
            if (baseGrams !== null) {
                multiplier = requestedGrams / baseGrams;
            } else {
                // Erro: tentando comprar gramas de algo que é vendido em "un". Usa a qtd como multi.
                multiplier = qtd;
            }
        }

        let estimadoTotal = multiplier * parseFloat(p.preco);

        document.getElementById('modalAddPesoEst').innerText = (requestedGrams >= 1000) 
            ? (requestedGrams / 1000).toFixed(3).replace('.', ',') + ' kg'
            : requestedGrams.toFixed(0) + ' g';
        
        document.getElementById('modalAddPrecoEst').innerText = 'R$ ' + estimadoTotal.toFixed(2).replace('.', ',');
    }

    function confirmarAdicaoModal() {
        if (!prodAdicaoAtual) return;
        let p = prodAdicaoAtual;
        let qtd = parseFloat(document.getElementById('modalAddQtd').value) || 0;
        
        if (qtd <= 0) return alert('Insira uma quantidade válida.');

        let itemExistente = carrinhoDados.find(i => i.id === p.id);
        
        let newItem = {
            id: p.id,
            nome: p.nome,
            preco_base: parseFloat(p.preco),
            unidade: p.unidade,
            tipo_venda: p.tipo_venda, // 'Inteiro' ou 'Fracionado'
            tipo_compra: tipoCompraAtual, // 'Unidade' ou 'Peso'
            input_qtd: qtd,
            peso_estimado_g: parseFloat(p.peso_estimado_g) || 0
        };

        // Calculando os valores exatos para o carrinho (quantidade que abate do BD e valor que aparece)
        let baseGrams = getBaseGrams(p.unidade);
        let multiplier = 0;
        let requestedGrams = 0;

        if (tipoCompraAtual === 'Unidade') {
            requestedGrams = qtd * newItem.peso_estimado_g;
            multiplier = (baseGrams !== null) ? (requestedGrams / baseGrams) : qtd;
        } else {
            requestedGrams = qtd;
            multiplier = (baseGrams !== null) ? (requestedGrams / baseGrams) : qtd;
        }

        newItem.quantidade_calculada = multiplier; // A fração que vai pro BD
        newItem.gramas_calculadas = requestedGrams; // Apenas para exibição
        newItem.preco_estimado_calculado = multiplier * newItem.preco_base;

        if (itemExistente) {
            // Se já tem no carrinho, sobrescrevemos ou somamos? Vamos somar o input_qtd e recalcular
            itemExistente.input_qtd = (itemExistente.input_qtd || itemExistente.quantidade || 0) + qtd;
            itemExistente.tipo_compra = tipoCompraAtual; // Força atualizar pro modelo atual
            
            if (tipoCompraAtual === 'Unidade') {
                itemExistente.gramas_calculadas = itemExistente.input_qtd * newItem.peso_estimado_g;
                itemExistente.quantidade_calculada = (baseGrams !== null) ? (itemExistente.gramas_calculadas / baseGrams) : itemExistente.input_qtd;
            } else {
                itemExistente.gramas_calculadas = itemExistente.input_qtd;
                itemExistente.quantidade_calculada = (baseGrams !== null) ? (itemExistente.gramas_calculadas / baseGrams) : itemExistente.gramas_calculadas;
            }
            itemExistente.preco_estimado_calculado = itemExistente.quantidade_calculada * newItem.preco_base;
        } else {
            carrinhoDados.push(newItem);
        }

        localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinhoDados));
        atualizarContador();
        document.getElementById('modalAdicionarProduto').style.display = 'none';
        
        let label = (tipoCompraAtual === 'Unidade') ? `${qtd}x` : `${qtd}g de`;
        alert(`${label} "${p.nome}" adicionado à sua cesta!`);
    }