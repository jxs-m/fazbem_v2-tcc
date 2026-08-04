document.addEventListener('DOMContentLoaded', carregarVitrine);

async function carregarVitrine() {
    const container = document.getElementById('lista-vitrine');
    const kitSecao = document.getElementById('kit-semana-secao');
    const kitTexto = document.getElementById('kit-semana-texto');

    // 1. Carregar Kit da Semana
    try {
        const configResponse = await fetch('api_config.php');
        const configJson = await configResponse.json();
        if (configJson.success && configJson.data && configJson.data.kit_semana) {
            kitTexto.innerText = configJson.data.kit_semana;
            kitSecao.style.display = 'block';
        }
    } catch (e) {
        console.error('Erro ao carregar kit da semana:', e);
    }

    // 2. Carregar Produtos
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
                    ? `<img src="${escapeHTML(window.getAbsoluteUrl(p.imagem_url))}" alt="${escapeHTML(p.nome)}" class="imagem-produto">`
                    : `<div class="sem-foto">📦</div>`;

                const tagCategoria = (parseInt(p.temporario) === 1) ? 'Temporários' : p.categoria;

                container.innerHTML += `
                <div class="card">
                    ${displayImagem}
                    <div class="categoria">${escapeHTML(tagCategoria)}</div>
                    <div class="nome">${escapeHTML(p.nome)}</div>
                    <div class="preco">R$ ${precoFormatado} <span style="font-size:14px; font-weight:normal; color:#6b7280;">/ ${escapeHTML(p.unidade)}</span></div>
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