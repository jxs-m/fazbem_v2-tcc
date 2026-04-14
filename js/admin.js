function showSection(id) {
            document.querySelectorAll('nav a').forEach(a => a.classList.remove('active'));
            document.getElementById('nav-' + id).classList.add('active');
            document.querySelectorAll('main > div').forEach(d => d.style.display = 'none');
            document.getElementById('sec-' + id).style.display = 'block';

            if (id === 'pedidos') carregarPedidos();
            if (id === 'produtos') carregarProdutos();
            if (id === 'clientes') carregarClientes();
            if (id === 'dashboard') carregarDashboardCounts();
        }

        async function carregarPedidos() {
            const tbody = document.getElementById('lista-pedidos');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">Carregando...</td></tr>';
            try {
                const res = await fetch('api_admin_pedidos_v2.php');
                const json = await res.json();
                if (json.success) {
                    tbody.innerHTML = '';

                    let totalFaturamento = 0;
                    document.getElementById('dash-pedidos').innerText = json.data.length;

                    json.data.forEach(p => {
                        totalFaturamento += parseFloat(p.valor_total);

                        const dataF = new Date(p.data_pedido).toLocaleDateString('pt-BR');
                        const totalF = parseFloat(p.valor_total).toFixed(2).replace('.', ',');

                        const selPag = `<select onchange="atualizarStatus(${p.id}, 'pagamento', this.value)" class="select-status ${p.status_pagamento === 'Pago' ? 'st-pago' : 'st-pendente'}"><option value="Pendente" ${p.status_pagamento === 'Pendente' ? 'selected' : ''}>Pendente</option><option value="Pago" ${p.status_pagamento === 'Pago' ? 'selected' : ''}>Pago</option><option value="Cancelado" ${p.status_pagamento === 'Cancelado' ? 'selected' : ''}>Cancelado</option></select>`;
                        const selEnt = `<select onchange="atualizarStatus(${p.id}, 'entrega', this.value)" class="select-status"><option value="Em separação" ${p.status_entrega === 'Em separação' ? 'selected' : ''}>Em separação</option><option value="Saiu para entrega" ${p.status_entrega === 'Saiu para entrega' ? 'selected' : ''}>Saiu para entrega</option><option value="Entregue" ${p.status_entrega === 'Entregue' ? 'selected' : ''}>Entregue</option></select>`;

                        tbody.innerHTML += `<tr><td>#${p.id}</td><td><strong>${escapeHTML(p.cliente)}</strong></td><td>${dataF}</td><td>R$ ${totalF}</td><td>${selPag}</td><td>${selEnt}</td><td><button class="btn btn-view" onclick="verDetalhes(${p.id})">Ver Itens</button></td></tr>`;
                    });

                    // Atualiza o Card de Faturamento
                    document.getElementById('dash-faturamento').innerText = 'R$ ' + totalFaturamento.toFixed(2).replace('.', ',');
                }
            } catch (e) { tbody.innerHTML = '<tr><td colspan="7">Erro</td></tr>'; }
        }


        async function carregarDashboardV2() {
            try {
                const response = await fetch('api_admin_dashboard_v2.php');
                const json = await response.json();

                if (json.success) {

                    let faturamento = parseFloat(json.data.faturamento).toFixed(2).replace('.', ',');

                    document.getElementById('dash-pedidos').innerText = json.data.total_pedidos;
                    document.getElementById('dash-faturamento').innerText = 'R$ ' + faturamento;
                    document.getElementById('dash-estoque').innerText = json.data.estoque_critico;
                    document.getElementById('dash-clientes').innerText = json.data.total_clientes;
                } else {
                    console.error("Erro no dashboard:", json.message);
                }
            } catch (error) {
                console.error("Falha ao comunicar com a API do Dashboard.");
            }
        }
        async function verDetalhes(id) {
            document.getElementById('modalDetalhes').style.display = 'flex';
            document.getElementById('det-itens').innerHTML = 'Carregando...';
            document.getElementById('det-total').innerText = '...';

            try {
                const res = await fetch(`api_admin_pedidos_v2.php?id=${id}`);
                const json = await res.json();
                if (json.success) {
                    const info = json.info;
                    document.getElementById('det-id').innerText = info.id;
                    document.getElementById('det-cliente').innerHTML = `<div style="font-size:18px; font-weight:bold; margin-bottom:5px;">${escapeHTML(info.nome)}</div><div>📞 ${escapeHTML(info.telefone)}</div><div style="margin-top:8px;">📍 ${escapeHTML(info.endereco)}</div><small>Ref: ${escapeHTML(info.ponto_referencia || 'Sem referência')}</small>`;

                    const divItens = document.getElementById('det-itens');
                    divItens.innerHTML = '';

                    let somaTotal = 0;

                    json.itens.forEach(i => {
                        let subtotal = i.quantidade * i.preco_unitario;
                        somaTotal += subtotal;

                        divItens.innerHTML += `<div class="item-row"><span class="check-box"></span><div><strong>${i.quantidade}x</strong> ${escapeHTML(i.nome)} <small>(${escapeHTML(i.unidade)})</small></div></div>`;
                    });


                    document.getElementById('det-total').innerText = 'Total: R$ ' + somaTotal.toFixed(2).replace('.', ',');

                    const divObs = document.getElementById('det-obs');
                    if (info.obs_pontual) { divObs.style.display = 'block'; divObs.innerHTML = '⚠ OBS: ' + escapeHTML(info.obs_pontual); } else { divObs.style.display = 'none'; }
                }
            } catch (e) { alert('Erro'); }
        }

        async function atualizarStatus(id, tipo, valor) {
            if (!confirm(`Alterar status?`)) { carregarPedidos(); return; }
            await fetch('api_admin_pedidos_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, tipo, valor }) });
            carregarPedidos();
        }


        async function carregarProdutos() {
            const tbody = document.getElementById('lista-produtos');
            try {
                const res = await fetch('api_admin_produtos_v2.php'); const json = await res.json();
                if (json.success) {
                    tbody.innerHTML = ''; let critico = 0;
                    json.data.forEach(p => {
                        if (p.estoque_atual < 10) critico++;
                        tbody.innerHTML += `<tr><td><strong>${escapeHTML(p.nome)}</strong></td><td>${escapeHTML(p.categoria)}</td><td>R$ ${parseFloat(p.preco).toFixed(2).replace('.', ',')} / ${escapeHTML(p.unidade)}</td><td class="${p.estoque_atual < 10 ? 'low-stock' : ''}">${p.estoque_atual}</td><td style="text-align:right"><button class="btn btn-edit" onclick='editarProd(${JSON.stringify(p)})'>✏️</button><button class="btn btn-danger" onclick="deletarProd(${p.id})">🗑️</button></td></tr>`;
                    });
                    document.getElementById('dash-estoque').innerText = critico;
                }
            } catch (e) { tbody.innerHTML = '<tr><td colspan="5">Erro</td></tr>'; }
        }

        async function salvarProduto() {
            const formData = new FormData();

            formData.append('id', document.getElementById('prodId').value);
            formData.append('nome', document.getElementById('prodNome').value);
            formData.append('categoria', document.getElementById('prodCategoria').value);
            formData.append('preco', document.getElementById('prodPreco').value);
            formData.append('unidade', document.getElementById('prodUnidade').value);
            formData.append('estoque', document.getElementById('prodEstoque').value);

            const inputFoto = document.getElementById('prodFotoInput');
            if (inputFoto.files && inputFoto.files.length > 0) {
                formData.append('imagem', inputFoto.files[0]);
            }

            if (!document.getElementById('prodNome').value) return alert("Preencha o nome.");

            const btn = document.getElementById('btnSalvarProd');
            btn.innerText = "Salvando..."; btn.disabled = true;

            try {
                const res = await fetch('api_admin_produtos_v2.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();

                if (json.success) {
                    alert('Salvo!');
                    fecharModal('modalProduto');
                    carregarProdutos();
                } else {
                    alert('Erro: ' + json.message);
                }
            } catch (e) {
                alert('Erro de conexão ao salvar produto.');
            } finally {
                btn.innerText = "Salvar"; btn.disabled = false;
            }
        }


        async function deletarProd(id) {
            if (!confirm('Excluir?')) return;
            await fetch('api_admin_produtos_v2.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
            carregarProdutos();
        }

        let todosClientes = [];

        async function carregarClientes() {
            const tbody = document.getElementById('lista-clientes'); tbody.innerHTML = '<tr><td colspan="7">Carregando...</td></tr>';
            try { const res = await fetch('api_admin_clientes_v2.php'); const json = await res.json(); if (json.success) { todosClientes = json.data; renderizarClientes(todosClientes); document.getElementById('dash-clientes').innerText = json.data.length; } } catch (e) { tbody.innerHTML = '<tr><td colspan="7">Erro</td></tr>'; }
        }

        function renderizarClientes(lista) {
            const tbody = document.getElementById('lista-clientes'); tbody.innerHTML = '';
            if (lista.length === 0) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">Nenhum cliente.</td></tr>'; return; }
            lista.forEach(c => {
                let corSt = c.status === 'Ativa' ? '#166534' : (c.status === 'Pausada' ? '#9a3412' : '#991b1b');
                let bgSt = c.status === 'Ativa' ? '#dcfce7' : (c.status === 'Pausada' ? '#ffedd5' : '#fee2e2');
                let freqBadge = c.frequencia === 'Semanal' ? '📅 Semanal' : '🗓 Quinzenal';
                let totalGasto = parseFloat(c.total_gasto || 0).toFixed(2).replace('.', ',');
                tbody.innerHTML += `<tr><td><strong>${escapeHTML(c.nome)}</strong><br><small>${escapeHTML(c.email)}</small></td><td>${escapeHTML(c.telefone)}</td><td><small>${escapeHTML(c.endereco)}</small></td><td><strong>${freqBadge}</strong></td><td style="color:#2b8a3e;font-weight:bold">R$ ${totalGasto}</td><td><span style="background:${bgSt}; color:${corSt}; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold">${c.status || 'Inativo'}</span></td><td><button class="btn btn-edit" onclick='editarCliente(${JSON.stringify(c)})'>✏️ Editar</button></td></tr>`;
            });
        }

        function filtrarClientes(tipo) { if (tipo === 'Todos') renderizarClientes(todosClientes); else renderizarClientes(todosClientes.filter(c => c.frequencia === tipo)); }

        function editarCliente(c) {
            document.getElementById('modalCliente').style.display = 'flex';
            document.getElementById('cliId').value = c.id; document.getElementById('cliNome').value = c.nome; document.getElementById('cliEmail').value = c.email; document.getElementById('cliTelefone').value = c.telefone; document.getElementById('cliEndereco').value = c.endereco;
            document.getElementById('cliFrequencia').value = c.frequencia || 'Semanal'; document.getElementById('cliStatus').value = c.status || 'Ativa';
        }

        async function salvarCliente() {
            const dados = { id: document.getElementById('cliId').value, nome: document.getElementById('cliNome').value, telefone: document.getElementById('cliTelefone').value, endereco: document.getElementById('cliEndereco').value, frequencia: document.getElementById('cliFrequencia').value, status: document.getElementById('cliStatus').value };
            const btn = document.getElementById('btnSalvarCli'); btn.innerText = "Salvando..."; btn.disabled = true;
            try { const res = await fetch('api_admin_clientes_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados) }); const json = await res.json(); if (json.success) { alert('Atualizado!'); fecharModal('modalCliente'); carregarClientes(); } else { alert('Erro: ' + json.message); } } catch (e) { alert('Erro'); } finally { btn.innerText = "Salvar Alterações"; btn.disabled = false; }
        }

        function carregarDashboardCounts() { carregarDashboardV2(); carregarPedidos(); carregarProdutos(); carregarClientes(); }
        function abrirModalProd() { document.getElementById('modalProduto').style.display = 'flex'; document.getElementById('prodId').value = ''; document.getElementById('prodNome').value = ''; document.getElementById('prodPreco').value = ''; document.getElementById('prodUnidade').value = ''; document.getElementById('prodEstoque').value = ''; document.getElementById('prodFotoInput').value = ''; document.getElementById('prodFotoBase64').value = ''; document.getElementById('previewImg').style.display = 'none'; document.getElementById('modalTitle').innerText = 'Novo Produto'; }
        function editarProd(p) { document.getElementById('modalProduto').style.display = 'flex'; document.getElementById('modalTitle').innerText = 'Editar Produto'; document.getElementById('prodId').value = p.id; document.getElementById('prodNome').value = p.nome; document.getElementById('prodCategoria').value = p.categoria; document.getElementById('prodPreco').value = p.preco; document.getElementById('prodUnidade').value = p.unidade; document.getElementById('prodEstoque').value = p.estoque_atual; const preview = document.getElementById('previewImg'); const hidden = document.getElementById('prodFotoBase64'); if (p.imagem_url) { preview.src = p.imagem_url; preview.style.display = 'block'; hidden.value = p.imagem_url; } else { preview.style.display = 'none'; hidden.value = ''; } }
        function fecharModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function (e) { if (e.target.className === 'modal') e.target.style.display = 'none'; }

        carregarDashboardCounts();
        function mostrarPreview(input) {
            if (input.files && input.files[0]) {
                document.getElementById('previewImg').src = URL.createObjectURL(input.files[0]);
                document.getElementById('previewImg').style.display = 'block';
            }
        }