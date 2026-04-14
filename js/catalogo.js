let carrinhoDados = JSON.parse(localStorage.getItem('fazbem_carrinho')) || [];
    atualizarContador();

    const icones = {
      'Legumes': '🍅',
      'Verduras': '🥬',
      'Frutas': '🍊',
      'Processados': '🥕',
      'Outros': '📦'
    };

    const descricoes = {
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
          const ordem = ['Legumes', 'Verduras', 'Frutas', 'Processados', 'Outros'];

          ordem.forEach(cat => {
            if (grupos[cat] && grupos[cat].length > 0) {
              renderizarSecao(container, cat, grupos[cat]);
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
        const cat = produto.categoria;
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(produto);
        return acc;
      }, {});
    }

    function renderizarSecao(container, categoria, produtos) {
      const section = document.createElement('section');
      const iconeCat = icones[categoria] || '📦';
      const desc = descricoes[categoria] || '';

      let htmlProdutos = '';

      produtos.forEach(p => {
        const precoFormatado = parseFloat(p.preco).toFixed(2).replace('.', ',');


        const imagemDisplay = p.imagem_url
          ? `<img src="${escapeHTML(p.imagem_url)}" alt="${escapeHTML(p.nome)}" class="imagem-produto">`
          : `<span class="prod-icon">${iconeCat}</span>`;
        htmlProdutos += `
          <div class="card">
            ${imagemDisplay}
            <div class="prod-name">${escapeHTML(p.nome)}</div>
            <div class="prod-price">R$ ${precoFormatado} <span class="prod-unit">/ ${escapeHTML(p.unidade)}</span></div>
            <div class="actions">
              <button class="btn-add" onclick="adicionarCarrinho('${escapeHTML(p.nome.replace(/'/g, "\\'"))}', ${p.id}, '${p.preco}')">
                + Adicionar
              </button>
              <button class="btn-swap" onclick="abrirTroca('${escapeHTML(p.nome.replace(/'/g, "\\'"))}')">
                ⇄ Trocar Item
              </button>
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

    function adicionarCarrinho(nome, id, preco) {
      let itemExistente = carrinhoDados.find(i => i.id === id);
      if (itemExistente) {
        itemExistente.quantidade++;
      } else {
        carrinhoDados.push({
          id: id,
          nome: nome,
          preco: parseFloat(preco),
          quantidade: 1
        });
      }
      localStorage.setItem('fazbem_carrinho', JSON.stringify(carrinhoDados));
      atualizarContador();
      alert(`"${nome}" foi adicionado à sua cesta!`);
    }

    function atualizarContador() {
      let totalItens = carrinhoDados.reduce((acc, item) => acc + item.quantidade, 0);
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