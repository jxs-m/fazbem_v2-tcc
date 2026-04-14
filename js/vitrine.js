document.addEventListener('DOMContentLoaded', carregarVitrine);

        async function carregarVitrine() {
            const container = document.getElementById('lista-vitrine');

            try {
                const response = await fetch('api_catalogo_v2.php');
                const json = await response.json();

                if (json.success) {
                    container.innerHTML = '';

                    if (json.data.length === 0) {
                        container.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">Nenhum produto cadastrado ainda.</p>';
                        return;
                    }

                    json.data.forEach(p => {
                        const precoFormatado = parseFloat(p.preco).toFixed(2).replace('.', ',');

                        const displayImagem = p.imagem_url
                            ? `<img src="${escapeHTML(p.imagem_url)}" alt="${escapeHTML(p.nome)}" class="imagem-produto">`
                            : `<div class="sem-foto">📦</div>`;

                        container.innerHTML += `
                        <div class="card">
                            ${displayImagem}
                            <div class="categoria">${escapeHTML(p.categoria)}</div>
                            <div class="nome">${escapeHTML(p.nome)}</div>
                            <div class="preco">R$ ${precoFormatado} <span style="font-size:14px; font-weight:normal; color:#6b7280;">/ ${escapeHTML(p.unidade)}</span></div>
                            <button class="btn-comprar" onclick="alert('Produto selecionado: ' + escapeHTML(p.nome))">Adicionar</button>
                        </div>
                    `;
                    });
                } else {
                    container.innerHTML = `<p style="grid-column: 1/-1; color:red;">Erro: ${escapeHTML(json.message)}</p>`;
                }
            } catch (error) {
                container.innerHTML = '<p style="grid-column: 1/-1; color:red;">Falha de conexão com a API.</p>';
            }
        }