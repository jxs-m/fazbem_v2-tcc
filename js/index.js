// js/index.js

document.addEventListener('DOMContentLoaded', () => {
    verificarSessao();
    carregarCatalogoDestaques();
    inicializarFiltros();
});

let produtosGlobais = [];

/**
 * Verifica a sessão ativa do usuário no backend e ajusta a navbar de acordo.
 */
async function verificarSessao() {
    const navbarActions = document.getElementById('navbar-actions');
    if (!navbarActions) return;

    try {
        const response = await fetch('api_sessao_v2.php');
        const json = await response.json();

        if (json.success && json.logado) {
            let dashboardUrl = 'perfil.html';
            let labelDashboard = 'Minha Conta';

            if (json.usuario.tipo === 'admin') {
                dashboardUrl = 'admin.html';
                labelDashboard = 'Painel Admin';
            } else if (json.usuario.tipo === 'entregador') {
                dashboardUrl = 'entregador.html';
                labelDashboard = 'Painel Entregador';
            } else if (json.usuario.tipo === 'separador') {
                dashboardUrl = 'separador.html';
                labelDashboard = 'Painel Separador';
            }

            // Exibe saudação e link para o painel apropriado mais botão de Sair (logout.php)
            const primeNome = escapeHTML(json.usuario.nome.split(' ')[0]);
            navbarActions.innerHTML = `
                <span class="user-greeting" style="font-size: 14.5px; color: var(--text-muted); font-weight: 500; margin-right: 10px;">
                    Olá, <strong style="color: var(--primary);">${primeNome}</strong>!
                </span>
                <a href="${dashboardUrl}" class="btn btn-dashboard" id="nav-dashboard-btn" style="padding: 8px 16px; font-size:13.5px;">${labelDashboard}</a>
                <a href="logout.php" class="btn-login" id="nav-logout-btn" style="color: #dc2626; font-size:14px; font-weight:600; padding: 8px 12px;">Sair</a>
            `;
        }
    } catch (err) {
        console.error("Falha ao verificar sessão do usuário", err);
    }
}

/**
 * Carrega os produtos da API e atualiza os contadores e status do catálogo.
 */
async function carregarCatalogoDestaques() {
    const grid = document.getElementById('featured-products-grid');
    const statusBadge = document.getElementById('catalog-status-badge');
    const kitsBadgeWrapper = document.getElementById('kits-badge-wrapper');
    const kitsCountNumber = document.getElementById('kits-count-number');

    if (!grid) return;

    try {
        const response = await fetch('api_catalogo_v2.php');
        const json = await response.json();

        if (json.success) {
            produtosGlobais = json.data || [];
            
            // Catálogo Status
            if (statusBadge) {
                if (json.isOpen) {
                    statusBadge.textContent = 'Catálogo Aberto';
                    statusBadge.className = 'status-badge open';
                    
                    // Exibe contador de kits se catálogo aberto
                    if (kitsBadgeWrapper && kitsCountNumber) {
                        kitsBadgeWrapper.style.display = 'inline-block';
                        kitsCountNumber.textContent = json.kitsDisponiveis;
                    }
                } else {
                    statusBadge.textContent = 'Catálogo Fechado';
                    statusBadge.className = 'status-badge closed';
                    if (kitsBadgeWrapper) {
                        kitsBadgeWrapper.style.display = 'none';
                    }
                }
            }

            renderizarProdutos(produtosGlobais);
        } else {
            grid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: red;">Erro ao carregar os destaques.</p>`;
        }
    } catch (err) {
        console.error("Erro ao carregar catálogo para a vitrine da home", err);
        grid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: red;">Falha de comunicação com o servidor.</p>`;
    }
}

/**
 * Renderiza uma lista filtrada de produtos orgânicos no grid.
 * @param {Array} lista 
 */
function renderizarProdutos(lista) {
    const grid = document.getElementById('featured-products-grid');
    if (!grid) return;

    grid.innerHTML = '';

    if (lista.length === 0) {
        grid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 60px 0; font-size:15px;">Nenhum produto cadastrado nesta categoria no momento.</p>`;
        return;
    }

    // Mostrar no máximo 8 produtos na home page
    const limite = lista.slice(0, 8);

    limite.forEach(p => {
        const precoFormatado = parseFloat(p.preco).toFixed(2).replace('.', ',');
        const displayImagem = p.imagem_url 
            ? `<img src="${escapeHTML(p.imagem_url)}" alt="${escapeHTML(p.nome)}" class="product-image">`
            : `<div class="product-no-image">📦</div>`;

        const tagCategoria = (parseInt(p.temporario) === 1) ? 'Temporários' : p.categoria;

        const durationDisplay = p.dias_restantes 
            ? `<div style="margin-top: 4px; margin-bottom: 4px;"><span class="prod-duration" style="background: #fffbeb; color: #b45309; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; border: 1px solid #fde68a; display: inline-block;">⏳ Restam ${p.dias_restantes} ${p.dias_restantes === 1 ? 'dia' : 'dias'}</span></div>`
            : '';

        grid.innerHTML += `
            <div class="product-card">
                <div class="product-image-wrapper">
                    ${displayImagem}
                    <span class="product-category-tag">${escapeHTML(tagCategoria)}</span>
                </div>
                <div class="product-details">
                    <h3 class="product-title">${escapeHTML(p.nome)}</h3>
                    ${durationDisplay}
                    <div class="product-price-row">
                        <span class="product-price">R$ ${precoFormatado}</span>
                        <span class="product-unit">/ ${escapeHTML(p.unidade)}</span>
                    </div>
                    <a href="catalogo.html" class="product-btn-add">Adicionar Extra</a>
                </div>
            </div>
        `;
    });
}

/**
 * Inicializa os ouvintes de clique nos botões de filtros de categoria.
 */
function inicializarFiltros() {
    const botoes = document.querySelectorAll('.category-filters .filter-btn');
    botoes.forEach(btn => {
        btn.addEventListener('click', () => {
            botoes.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const categoria = btn.getAttribute('data-category');
            if (categoria === 'Todos') {
                renderizarProdutos(produtosGlobais);
            } else if (categoria === 'Temporários') {
                const filtrados = produtosGlobais.filter(p => parseInt(p.temporario) === 1);
                renderizarProdutos(filtrados);
            } else {
                const filtrados = produtosGlobais.filter(p => p.categoria.toLowerCase() === categoria.toLowerCase() && parseInt(p.temporario) !== 1);
                renderizarProdutos(filtrados);
            }
        });
    });
}
