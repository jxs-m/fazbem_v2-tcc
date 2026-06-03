function showSection(id) {
    document.querySelectorAll('nav a').forEach(a => a.classList.remove('active'));
    document.getElementById('nav-' + id).classList.add('active');
    document.querySelectorAll('main > div').forEach(d => d.style.display = 'none');
    document.getElementById('sec-' + id).style.display = 'block';

    if (id === 'pedidos') carregarPedidos();
    if (id === 'produtos') carregarProdutos();
    if (id === 'producao') carregarProducao();
    if (id === 'clientes') carregarClientes();
    if (id === 'funcionarios') carregarFuncionarios();
    if (id === 'rotas') carregarRotas();
    if (id === 'faturamento') carregarFaturasAdmin();
    if (id === 'dashboard') carregarDashboardCounts();
}

async function carregarPedidos() {
    const tbody = document.getElementById('lista-pedidos');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Carregando...</td></tr>';
    try {
        const res = await fetch('api_admin_pedidos_v2.php');
        const json = await res.json();
        if (json.success) {
            tbody.innerHTML = '';

            json.data.forEach(p => {
                const dataF = new Date(p.data_pedido).toLocaleDateString('pt-BR');
                const totalF = parseFloat(p.valor_total).toFixed(2).replace('.', ',');

                const selEnt = `<select onchange="atualizarStatus(${p.id}, 'entrega', this.value)" class="select-status"><option value="Em separação" ${p.status_entrega === 'Em separação' ? 'selected' : ''}>Em separação</option><option value="Aguardando Entrega" ${p.status_entrega === 'Aguardando Entrega' ? 'selected' : ''}>Aguardando Entrega</option><option value="Saiu para entrega" ${p.status_entrega === 'Saiu para entrega' ? 'selected' : ''}>Saiu para entrega</option><option value="Entregue" ${p.status_entrega === 'Entregue' ? 'selected' : ''}>Entregue</option></select>`;

                let btnComprovante = '';
                if (p.status_pagamento === 'Pago') {
                    btnComprovante = `<a href="comprovante.php?tipo=pedido&id=${p.id}" target="_blank" class="btn" style="background:#1d4ed8; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:12px; margin-left:5px; display:inline-block;">Comprovante</a>`;
                }

                tbody.innerHTML += `<tr><td>#${p.id}</td><td><strong>${escapeHTML(p.cliente)}</strong></td><td>${dataF}</td><td>R$ ${totalF}</td><td>${selEnt}</td><td><button class="btn btn-view" onclick="verDetalhes(${p.id})">Ver Itens</button>${btnComprovante}</td></tr>`;
            });

        }
    } catch (e) { tbody.innerHTML = '<tr><td colspan="6">Erro</td></tr>'; }
}


async function carregarDashboardV2() {
    try {
        const response = await fetch('api_admin_dashboard_v2.php');
        const json = await response.json();

        if (json.success) {

            let faturamento = parseFloat(json.data.faturamento || 0).toFixed(2).replace('.', ',');
            let fatEsperadoMes = parseFloat(json.data.faturamento_esperado_mes || 0).toFixed(2).replace('.', ',');
            let fatEsperado = parseFloat(json.data.faturamento_esperado || 0).toFixed(2).replace('.', ',');
            let fatMes = parseFloat(json.data.faturamento_mes || 0).toFixed(2).replace('.', ',');
            let creditos = parseFloat(json.data.total_creditos || 0).toFixed(2).replace('.', ',');

            document.getElementById('dash-pedidos').innerText = json.data.total_pedidos;
            document.getElementById('dash-faturamento').innerText = 'R$ ' + faturamento;
            
            if (document.getElementById('dash-faturamento-esperado-mes')) document.getElementById('dash-faturamento-esperado-mes').innerText = 'R$ ' + fatEsperadoMes;
            if (document.getElementById('dash-faturamento-esperado')) document.getElementById('dash-faturamento-esperado').innerText = 'R$ ' + fatEsperado;
            if (document.getElementById('dash-faturamento-mes')) document.getElementById('dash-faturamento-mes').innerText = 'R$ ' + fatMes;

            document.getElementById('dash-estoque').innerText = json.data.estoque_critico;
            document.getElementById('dash-clientes').innerText = json.data.total_clientes;
            document.getElementById('dash-creditos').innerText = 'R$ ' + creditos;

            const tbodyPausados = document.getElementById('lista-pausados');
            const tbodyCancelados = document.getElementById('lista-cancelados');
            
            tbodyPausados.innerHTML = '';
            tbodyCancelados.innerHTML = '';
            
            let countPausados = 0;
            let countCancelados = 0;
            
            if (json.data.assinantes_inativos && json.data.assinantes_inativos.length > 0) {
                json.data.assinantes_inativos.forEach(c => {
                    const phoneWpp = c.telefone.replace(/\D/g, '');
                    const linkWpp = `https://wa.me/55${phoneWpp}?text=${encodeURIComponent('Olá ' + c.nome + ', sentimos sua falta na Faz Bem! Gostaria de reativar sua assinatura de orgânicos?')}`;
                    
                    const tr = `
                        <tr>
                            <td><strong>${escapeHTML(c.nome)}</strong></td>
                            <td>${escapeHTML(c.telefone)}</td>
                            <td style="text-align:right">
                                <a href="${linkWpp}" target="_blank" class="btn" style="background:#25D366; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:12px;">💬 Chamar no WhatsApp</a>
                            </td>
                        </tr>
                    `;

                    if (c.status === 'Pausada') {
                        tbodyPausados.innerHTML += tr;
                        countPausados++;
                    } else if (c.status === 'Cancelada') {
                        tbodyCancelados.innerHTML += tr;
                        countCancelados++;
                    }
                });
            }
            
            if (countPausados === 0) {
                tbodyPausados.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#666;">Nenhuma assinatura pausada.</td></tr>';
            }
            if (countCancelados === 0) {
                tbodyCancelados.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#666;">Nenhuma assinatura cancelada.</td></tr>';
            }
        } else {
            console.error("Erro no dashboard:", json.message);
        }
    } catch (error) {
        console.error("Falha ao comunicar com a API do Dashboard.");
    }
}
function getBaseGrams(unidadeStr) {
    let u = (unidadeStr || '').toLowerCase();
    if (u.includes('kg')) return 1000;
    if (u === 'g') return 1;
    if (u.includes('g') && !u.includes('kg')) {
       let num = parseInt(u);
       if (!isNaN(num) && num > 0) return num;
    }
    return null;
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
            const phoneWpp = info.telefone.replace(/\D/g, '');
            const linkWpp = `https://wa.me/55${phoneWpp}?text=${encodeURIComponent('Olá ' + info.nome + ', somos da Faz Bem! Referente ao seu pedido #' + info.id + '...')}`;
            document.getElementById('det-cliente').innerHTML = `<div style="font-size:18px; font-weight:bold; margin-bottom:5px;">${escapeHTML(info.nome)}</div><div>📞 ${escapeHTML(info.telefone)} <a href="${linkWpp}" target="_blank" style="background:#25D366; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:12px; margin-left:8px; display:inline-block">💬 WhatsApp</a></div><div style="margin-top:8px;">📍 ${escapeHTML(info.endereco)}</div><small>Ref: ${escapeHTML(info.ponto_referencia || 'Sem referência')}</small>`;
            if (info.status_entrega === 'Entregue' && info.entregue_em) {
                document.getElementById('det-cliente').innerHTML += `<div style="margin-top:8px; color: #166534; font-weight: bold; background: #dcfce7; padding: 4px 8px; border-radius: 4px; display: inline-block;">✅ Entregue em: ${new Date(info.entregue_em).toLocaleString('pt-BR')}</div>`;
            }

            const divItens = document.getElementById('det-itens');
            divItens.innerHTML = '';

            let somaTotal = 0;
            let temPesagem = false;

            json.itens.forEach(i => {
                let baseGrams = getBaseGrams(i.unidade);
                let qtyDisplay = '';
                let subtotal = 0;

                let isWeighed = i.quantidade_real !== null;
                if (isWeighed) {
                    temPesagem = true;
                }

                if (i.tipo_venda === 'Fracionado') {
                    let qtyRequestedG = baseGrams !== null ? Math.round(parseFloat(i.quantidade) * baseGrams) : i.quantidade;
                    qtyDisplay = `Solicitado: ${qtyRequestedG}g`;
                    
                    if (isWeighed) {
                        if (parseFloat(i.quantidade_real) === 0) {
                            qtyDisplay += ` ➔ <strong style="color:#dc2626;">[EM FALTA]</strong>`;
                        } else {
                            let qtyRealG = baseGrams !== null ? Math.round(parseFloat(i.quantidade_real) * baseGrams) : i.quantidade_real;
                            qtyDisplay += ` ➔ <strong>Pesado: ${qtyRealG}g</strong>`;
                        }
                        subtotal = parseFloat(i.preco_real || (i.quantidade_real * i.preco_unitario));
                    } else {
                        subtotal = parseFloat(i.quantidade * i.preco_unitario);
                    }
                } else {
                    let qtyRequestedUn = baseGrams !== null && parseFloat(i.peso_estimado_g) > 0 
                        ? Math.round((parseFloat(i.quantidade) * baseGrams) / parseFloat(i.peso_estimado_g))
                        : parseFloat(i.quantidade);
                    qtyDisplay = `${qtyRequestedUn} un`;

                    if (baseGrams !== null) {
                        let expectedG = Math.round(parseFloat(i.quantidade) * baseGrams);
                        qtyDisplay += ` (~${expectedG}g)`;

                        if (isWeighed) {
                            if (parseFloat(i.quantidade_real) === 0) {
                                qtyDisplay += ` ➔ <strong style="color:#dc2626;">[EM FALTA]</strong>`;
                            } else {
                                let qtyRealG = Math.round(parseFloat(i.quantidade_real) * baseGrams);
                                qtyDisplay += ` ➔ <strong>Pesado: ${qtyRealG}g</strong>`;
                            }
                            subtotal = parseFloat(i.preco_real || (i.quantidade_real * i.preco_unitario));
                        } else {
                            subtotal = parseFloat(i.quantidade * i.preco_unitario);
                        }
                    } else {
                        if (isWeighed) {
                            if (parseFloat(i.quantidade_real) === 0) {
                                qtyDisplay += ` ➔ <strong style="color:#dc2626;">[EM FALTA]</strong>`;
                            } else {
                                qtyDisplay += ` ➔ <strong>Confirmado: ${i.quantidade_real} un</strong>`;
                            }
                            subtotal = parseFloat(i.preco_real || (i.quantidade_real * i.preco_unitario));
                        } else {
                            subtotal = parseFloat(i.quantidade * i.preco_unitario);
                        }
                    }
                }

                somaTotal += subtotal;
                let precoUnit = parseFloat(i.preco_unitario).toFixed(2).replace('.', ',');
                let subtotalF = subtotal.toFixed(2).replace('.', ',');

                divItens.innerHTML += `
                    <div class="item-row" style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #eee;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="check-box"></span>
                            <div>
                                <strong>${escapeHTML(i.nome)}</strong><br>
                                <span style="font-size:12px; color:#666;">${qtyDisplay} (R$ ${precoUnit} / ${i.unidade})</span>
                            </div>
                        </div>
                        <div style="font-weight:bold; color:#374151;">R$ ${subtotalF}</div>
                    </div>
                `;
            });

            let totalF = parseFloat(info.valor_total).toFixed(2).replace('.', ',');
            document.getElementById('det-total').innerHTML = `
                <div style="display:flex; flex-direction:column; align-items:flex-end; width:100%;">
                    <div style="font-size:18px; font-weight:bold; color:var(--green-primary);">Total: R$ ${totalF}</div>
                    <small style="color:#666; font-weight:normal; margin-top:4px;">
                        ${temPesagem ? '* Valores finais atualizados após a pesagem no galpão' : '* Valores provisórios aguardando pesagem'}
                    </small>
                </div>
            `;

            const divObs = document.getElementById('det-obs');
            if (info.obs_pontual) { divObs.style.display = 'block'; divObs.innerHTML = '⚠ OBS: ' + escapeHTML(info.obs_pontual); } else { divObs.style.display = 'none'; }

            const divExc = document.getElementById('det-excecoes');
            const txtExc = document.getElementById('det-excecoes-texto');
            if (json.preferencias && json.preferencias.length > 0) {
                let excText = '';
                json.preferencias.forEach(p => {
                    if (p.tipo === 'Troca Pontual') {
                        excText += `<span style="background: #ef4444; color: white; padding: 2px 4px; border-radius: 4px; font-size: 11px; margin-right: 5px; font-weight: bold;">DA SEMANA</span> ${escapeHTML(p.descricao)}<br>`;
                    } else {
                        excText += `- ${escapeHTML(p.descricao)}<br>`;
                    }
                });
                txtExc.innerHTML = excText;
                divExc.style.display = 'block';
            } else {
                divExc.style.display = 'none';
            }
        }
    } catch (e) { alert('Erro ao carregar detalhes'); }
}

async function atualizarStatus(id, tipo, valor) {
    if (!confirm(`Alterar status?`)) { carregarPedidos(); return; }
    try {
        const res = await fetch('api_admin_pedidos_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, tipo, valor }) });
        const json = await res.json();
        if (!json.success) {
            alert('Erro: ' + json.message);
        }
    } catch (e) {
        alert('Erro ao atualizar status.');
    }
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
                let estoqueFloat = parseFloat(p.estoque_atual);
                let estoqueDisplay = `${estoqueFloat} ${p.unidade}`;
                tbody.innerHTML += `<tr><td><strong>${escapeHTML(p.nome)}</strong></td><td>${escapeHTML(p.categoria)}</td><td>R$ ${parseFloat(p.preco).toFixed(2).replace('.', ',')} / ${escapeHTML(p.unidade)}</td><td class="${p.estoque_atual < 10 ? 'low-stock' : ''}">${estoqueDisplay}</td><td style="text-align:right"><button class="btn btn-edit" title="Ajustar Estoque" onclick='abrirModalEstoque(${JSON.stringify(p).replace(/'/g, "&#39;")})'>📦</button> <button class="btn btn-edit" title="Editar Informações" onclick='editarProd(${JSON.stringify(p).replace(/'/g, "&#39;")})'>✏️</button> <button class="btn btn-danger" onclick="deletarProd(${p.id})">🗑️</button></td></tr>`;
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
    formData.append('peso_estimado_g', document.getElementById('prodPesoG').value || 0);
    formData.append('tipo_venda', document.getElementById('prodTipoVenda').value);
    formData.append('temporario', document.getElementById('prodTemporario').checked ? 1 : 0);
    formData.append('duracao_dias', document.getElementById('prodDuracaoDias').value);

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
    try {
        const res = await fetch('api_admin_produtos_v2.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
        const json = await res.json();
        if (!json.success) {
            alert('Erro: ' + json.message);
        }
    } catch (e) {
        alert('Erro ao excluir produto.');
    }
    carregarProdutos();
}

async function carregarProducao() {
    const tbody = document.getElementById('lista-producao');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">Calculando necessidades...</td></tr>';
    try {
        const res = await fetch('api_producao_v2.php');
        const json = await res.json();
        if (json.success) {
            tbody.innerHTML = '';

            try {
                const resConfig = await fetch('api_config.php');
                const jsonConfig = await resConfig.json();
                if (jsonConfig.success && jsonConfig.data.kit_semana) {
                    document.getElementById('config-kit-semana').value = jsonConfig.data.kit_semana;
                }
            } catch(e) { console.error(e); }

            const kitsCount = parseInt(json.kitsNaSemana);
            const limite = parseInt(json.limiteMaximo);

            const dashKits = document.getElementById('dash-kits-semana');
            dashKits.innerText = kitsCount;
            if (kitsCount >= limite) {
                dashKits.style.color = 'var(--danger)';
            } else {
                dashKits.style.color = '#16a34a'; // verde
            }

            if (json.hortalicasNecessarias.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">Nenhum pedido engatilhado para a semana.</td></tr>';
                return;
            }

            json.hortalicasNecessarias.forEach(h => {
                tbody.innerHTML += `
                            <tr>
                                <td>#${h.id}</td>
                                <td><strong>${escapeHTML(h.nome)}</strong></td>
                                <td>${escapeHTML(h.unidade)}</td>
                                <td style="font-size: 18px; font-weight: bold; color: var(--green-primary);">${h.total_necessario}</td>
                            </tr>
                        `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4">Erro ao processar relatório</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4">Erro: ${e.message}</td></tr>`;
    }
}

async function salvarKitSemana() {
    const kitText = document.getElementById('config-kit-semana').value;
    try {
        const res = await fetch('api_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kit_semana: kitText })
        });
        const json = await res.json();
        if (json.success) {
            alert('Kit da Semana salvo com sucesso!');
        } else {
            alert('Erro: ' + json.message);
        }
    } catch(e) {
        alert('Erro ao salvar o Kit da Semana.');
    }
}

let todosClientes = [];

async function carregarClientes() {
    const tbody = document.getElementById('lista-clientes'); tbody.innerHTML = '<tr><td colspan="7">Carregando...</td></tr>';
    try { const res = await fetch('api_admin_clientes_v2.php'); const json = await res.json(); if (json.success) { todosClientes = json.data; renderizarClientes(todosClientes); document.getElementById('dash-clientes').innerText = json.data.length; } } catch (e) { tbody.innerHTML = '<tr><td colspan="7">Erro</td></tr>'; }
}

function renderizarClientes(lista) {
    const tbody = document.getElementById('lista-clientes'); tbody.innerHTML = '';
    if (lista.length === 0) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Nenhum cliente.</td></tr>'; return; }
    lista.forEach(c => {
        let corSt = c.status === 'Ativa' ? '#166534' : (c.status === 'Pausada' ? '#9a3412' : '#991b1b');
        let bgSt = c.status === 'Ativa' ? '#dcfce7' : (c.status === 'Pausada' ? '#ffedd5' : '#fee2e2');
        let freqBadge = c.frequencia === 'Semanal' ? '📅 Semanal' : '🗓 Quinzenal';
        let totalGasto = parseFloat(c.total_gasto || 0).toFixed(2).replace('.', ',');
        let pref = c.preferencias ? escapeHTML(c.preferencias) : '-';
        tbody.innerHTML += `<tr><td><strong>${escapeHTML(c.nome)}</strong><br><small>${escapeHTML(c.email)}</small></td><td>${escapeHTML(c.telefone)}</td><td><small>${escapeHTML(c.endereco)}</small></td><td><strong>${freqBadge}</strong></td><td style="color:#2b8a3e;font-weight:bold">R$ ${totalGasto}</td><td><span style="background:${bgSt}; color:${corSt}; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold">${c.status || 'Inativo'}</span></td><td><small>${pref}</small></td><td><button class="btn btn-edit" onclick='editarCliente(${JSON.stringify(c)})'>✏️ Editar</button></td></tr>`;
    });
}

function filtrarClientes(tipo) { if (tipo === 'Todos') renderizarClientes(todosClientes); else renderizarClientes(todosClientes.filter(c => c.frequencia === tipo)); }

let mapAdmin;
let markerAdmin;

function initMapAdmin() {
    const DEFAULT_LAT = -29.7603;
    const DEFAULT_LNG = -57.0811;
    mapAdmin = L.map('map-admin-cliente', {
        maxBounds: [
            [-30.05, -57.45],
            [-29.45, -56.85]
        ],
        maxBoundsViscosity: 1.0
    }).setView([DEFAULT_LAT, DEFAULT_LNG], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapAdmin);
    markerAdmin = L.marker([DEFAULT_LAT, DEFAULT_LNG], { draggable: true }).addTo(mapAdmin);
    markerAdmin.on('dragend', function (e) {
        const pos = markerAdmin.getLatLng();
        document.getElementById('cliLatitude').value = pos.lat;
        document.getElementById('cliLongitude').value = pos.lng;
    });
}

async function buscarEnderecoAdmin() {
    const endereco = document.getElementById('cliEndereco').value;
    if (!endereco) return alert("Por favor, digite o endereço.");

    try {
        const query = endereco.toLowerCase().includes('uruguaiana') ? endereco : endereco + ', Uruguaiana, RS, Brasil';
        const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
        const data = await res.json();
        if (data && data.length > 0) {
            const lat = parseFloat(data[0].lat);
            const lng = parseFloat(data[0].lon);
            mapAdmin.setView([lat, lng], 16);
            markerAdmin.setLatLng([lat, lng]);
            document.getElementById('cliLatitude').value = lat;
            document.getElementById('cliLongitude').value = lng;
        } else {
            alert("Endereço não localizado no mapa. Mova o pino.");
        }
    } catch (err) { alert("Erro na busca."); }
}

function editarCliente(c) {
    document.getElementById('modalCliente').style.display = 'flex';
    document.getElementById('cliId').value = c.id; document.getElementById('cliNome').value = c.nome; document.getElementById('cliEmail').value = c.email; document.getElementById('cliTelefone').value = c.telefone; document.getElementById('cliEndereco').value = c.endereco;
    document.getElementById('cliFrequencia').value = c.frequencia || 'Semanal'; document.getElementById('cliStatus').value = c.status || 'Ativa';

    document.getElementById('containerMapaCli').style.display = 'block';
    if (!mapAdmin) initMapAdmin();

    setTimeout(() => {
        mapAdmin.invalidateSize();
        if (c.latitude && c.longitude) {
            const lat = parseFloat(c.latitude);
            const lng = parseFloat(c.longitude);
            mapAdmin.setView([lat, lng], 16);
            markerAdmin.setLatLng([lat, lng]);
            document.getElementById('cliLatitude').value = lat;
            document.getElementById('cliLongitude').value = lng;
        } else {
            document.getElementById('cliLatitude').value = '';
            document.getElementById('cliLongitude').value = '';
        }
    }, 100);
}

async function salvarCliente() {
    const dados = { id: document.getElementById('cliId').value, nome: document.getElementById('cliNome').value, telefone: document.getElementById('cliTelefone').value, endereco: document.getElementById('cliEndereco').value, frequencia: document.getElementById('cliFrequencia').value, status: document.getElementById('cliStatus').value, latitude: document.getElementById('cliLatitude').value, longitude: document.getElementById('cliLongitude').value };
    const btn = document.getElementById('btnSalvarCli'); btn.innerText = "Salvando..."; btn.disabled = true;
    try { const res = await fetch('api_admin_clientes_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados) }); const json = await res.json(); if (json.success) { alert('Atualizado!'); fecharModal('modalCliente'); carregarClientes(); } else { alert('Erro: ' + json.message); } } catch (e) { alert('Erro'); } finally { btn.innerText = "Salvar Alterações"; btn.disabled = false; }
}

function carregarDashboardCounts() { carregarDashboardV2(); carregarPedidos(); carregarProdutos(); carregarClientes(); }
function abrirModalProd() { document.getElementById('modalProduto').style.display = 'flex'; document.getElementById('prodId').value = ''; document.getElementById('prodNome').value = ''; document.getElementById('prodPreco').value = ''; document.getElementById('prodUnidade').value = ''; document.getElementById('prodEstoque').value = ''; document.getElementById('prodPesoG').value = '0'; document.getElementById('prodTipoVenda').value = 'Inteiro'; document.getElementById('prodFotoInput').value = ''; document.getElementById('prodFotoBase64').value = ''; document.getElementById('prodTemporario').checked = false; document.getElementById('groupDuracao').style.display = 'none'; document.getElementById('prodDuracaoDias').value = ''; document.getElementById('previewImg').style.display = 'none'; document.getElementById('modalTitle').innerText = 'Novo Produto'; }

function abrirModalEstoque(p) {
    document.getElementById('modalEstoque').style.display = 'flex';
    document.getElementById('estProdId').value = p.id;
    document.getElementById('estProdNome').innerText = p.nome;
    
    const selectUni = document.getElementById('estUni');
    let isUnSelected = p.unidade === 'un' || p.unidade === 'unidade' || p.tipo_venda === 'Inteiro';
    let isGSelected = p.tipo_venda === 'Fracionado';
    selectUni.innerHTML = `
        <option value="un" ${isUnSelected && !isGSelected ? 'selected' : ''}>unidade(s)</option>
        <option value="g" ${isGSelected ? 'selected' : ''}>g</option>
        <option value="kg" ${p.unidade === 'kg' && !isUnSelected ? 'selected' : ''}>kg</option>
        <option value="maço" ${p.unidade === 'maço' ? 'selected' : ''}>maço(s)</option>
        <option value="pé" ${p.unidade === 'pé' ? 'selected' : ''}>pé(s)</option>
        <option value="${escapeHTML(p.unidade)}" ${!['un','kg','g','maço','pé'].includes(p.unidade) ? 'selected' : ''}>${escapeHTML(p.unidade)}</option>
    `;
    
    document.getElementById('estQtde').value = '';
    document.getElementById('estDesc').value = '';
    document.getElementById('estTipo').value = 'Entrada';
}

async function salvarMovimentacao() {
    const data = {
        produto_id: document.getElementById('estProdId').value,
        tipo: document.getElementById('estTipo').value,
        quantidade: document.getElementById('estQtde').value,
        unidade_escolhida: document.getElementById('estUni').value,
        descricao: document.getElementById('estDesc').value
    };

    if (!data.quantidade || data.quantidade <= 0) return alert('Insira uma quantidade válida!');

    const btn = document.getElementById('btnSalvarEstoque');
    btn.innerText = "Registrando..."; btn.disabled = true;

    try {
        const res = await fetch('api_estoque_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const json = await res.json();
        if (json.success) {
            fecharModal('modalEstoque');
            carregarProdutos();
            carregarDashboardV2();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch (e) {
        alert('Erro na conexão');
    } finally {
        btn.innerText = "Registrar"; btn.disabled = false;
    }
}

function editarProd(p) { document.getElementById('modalProduto').style.display = 'flex'; document.getElementById('modalTitle').innerText = 'Editar Produto'; document.getElementById('prodId').value = p.id; document.getElementById('prodNome').value = p.nome; document.getElementById('prodCategoria').value = p.categoria; document.getElementById('prodPreco').value = p.preco; document.getElementById('prodUnidade').value = p.unidade; document.getElementById('prodEstoque').value = parseFloat(p.estoque_atual); document.getElementById('prodPesoG').value = p.peso_estimado_g || 0; document.getElementById('prodTipoVenda').value = p.tipo_venda || 'Inteiro'; document.getElementById('prodTemporario').checked = (parseInt(p.temporario) === 1); document.getElementById('groupDuracao').style.display = (parseInt(p.temporario) === 1) ? 'block' : 'none'; document.getElementById('prodDuracaoDias').value = p.duracao_dias || ''; const preview = document.getElementById('previewImg'); const hidden = document.getElementById('prodFotoBase64'); if (p.imagem_url) { preview.src = p.imagem_url; preview.style.display = 'block'; hidden.value = p.imagem_url; } else { preview.style.display = 'none'; hidden.value = ''; } }
function fecharModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function (e) { if (e.target.className === 'modal') e.target.style.display = 'none'; }

carregarDashboardCounts();
function mostrarPreview(input) {
    if (input.files && input.files[0]) {
        document.getElementById('previewImg').src = URL.createObjectURL(input.files[0]);
        document.getElementById('previewImg').style.display = 'block';
    }
}

async function carregarFuncionarios() {
    const tbody = document.getElementById('lista-funcionarios');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Carregando...</td></tr>';
    try {
        const res = await fetch('api_admin_funcionarios_v2.php');
        const json = await res.json();
        if (json.success) {
            tbody.innerHTML = '';
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Nenhum funcionário cadastrado.</td></tr>';
                return;
            }
            json.data.forEach(e => {
                let badgeColor = e.tipo_usuario === 'admin' ? '#ef4444' : (e.tipo_usuario === 'separador' ? '#f59e0b' : '#3b82f6');
                let badgeBg = e.tipo_usuario === 'admin' ? '#fee2e2' : (e.tipo_usuario === 'separador' ? '#fef3c7' : '#dbeafe');
                
                tbody.innerHTML += `<tr>
                            <td><strong>${escapeHTML(e.nome)}</strong></td>
                            <td>${escapeHTML(e.email)}</td>
                            <td>${escapeHTML(e.telefone || '-')}</td>
                            <td><span style="background:${badgeBg}; color:${badgeColor}; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold; text-transform:uppercase">${escapeHTML(e.tipo_usuario)}</span></td>
                            <td style="text-align:right">
                                <button class="btn btn-danger" onclick="excluirFuncionario(${e.id})">🗑️ Excluir</button>
                            </td>
                        </tr>`;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar funcionários.</td></tr>';
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="5">Erro de conexão.</td></tr>';
    }
}

function abrirModalFuncionario() {
    document.getElementById('funcNome').value = '';
    document.getElementById('funcEmail').value = '';
    document.getElementById('funcSenha').value = '';
    document.getElementById('funcTelefone').value = '';
    document.getElementById('funcTipo').value = 'entregador';
    document.getElementById('modalFuncionario').style.display = 'flex';
}

async function salvarFuncionario() {
    const dados = {
        nome: document.getElementById('funcNome').value,
        email: document.getElementById('funcEmail').value,
        senha: document.getElementById('funcSenha').value,
        telefone: document.getElementById('funcTelefone').value,
        tipo_usuario: document.getElementById('funcTipo').value
    };

    if (!dados.nome || !dados.email || !dados.senha) {
        return alert('Por favor, preencha nome, email e senha.');
    }

    const btn = document.getElementById('btnSalvarFuncionario');
    btn.innerText = 'Salvando...';
    btn.disabled = true;

    try {
        const res = await fetch('api_admin_funcionarios_v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        const json = await res.json();
        if (json.success) {
            alert('Funcionário cadastrado com sucesso!');
            fecharModal('modalFuncionario');
            carregarFuncionarios();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch (error) {
        alert('Erro de conexão ao salvar funcionário.');
    } finally {
        btn.innerText = 'Cadastrar';
        btn.disabled = false;
    }
}

async function excluirFuncionario(id) {
    if (!confirm('Tem certeza que deseja excluir este funcionário?')) return;
    try {
        const res = await fetch('api_admin_funcionarios_v2.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            carregarFuncionarios();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch (error) {
        alert('Erro de conexão ao excluir funcionário.');
    }
}

let rotasAtuais = [];

async function carregarRotas() {
    const tbody = document.getElementById('lista-rotas');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Carregando...</td></tr>';
    try {
        const res = await fetch('api_logistica_v2.php');
        const json = await res.json();
        if (json.success) {
            rotasAtuais = json.data;
            tbody.innerHTML = '';
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Nenhuma entrega ativa no momento.</td></tr>';
                return;
            }
            json.data.sort((a, b) => (a.ordem_entrega || 9999) - (b.ordem_entrega || 9999));
            json.data.forEach((p, index) => {
                tbody.innerHTML += `<tr data-id="${p.pedido_id}">
                    <td style="text-align:center; cursor: grab;" title="Arraste para reordenar">
                        <span style="color:#9ca3af; font-size: 18px; margin-right: 5px;">☰</span>
                        <strong class="ordem-numero" style="font-size: 16px; color: #4b5563;">${index + 1}</strong>
                    </td>
                    <td>#${p.pedido_id}</td>
                    <td><strong>${escapeHTML(p.nome)}</strong></td>
                    <td><small>${escapeHTML(p.logradouro)}</small></td>
                    <td><span style="background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:10px; font-size:11px;">${p.status_entrega}</span></td>
                </tr>`;
            });

            if (window.rotasSortable) window.rotasSortable.destroy();
            window.rotasSortable = Sortable.create(tbody, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                handle: 'td:first-child',
                onUpdate: function () {
                    atualizarNumerosOrdem();
                }
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar rotas.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5">Erro de conexão.</td></tr>';
    }
}

function calcularDistanciaLocal(lat1, lon1, lat2, lon2) {
    const dx = lat1 - lat2;
    const dy = lon1 - lon2;
    return Math.sqrt(dx * dx + dy * dy);
}

function autoOrdenarRotas() {
    if (rotasAtuais.length === 0) return alert('Nenhuma rota ativa para ordenar.');

    let currentLat = -29.7603;
    let currentLng = -57.0811;

    let unvisited = [...rotasAtuais];
    let order = 1;

    while (unvisited.length > 0) {
        let nearestIdx = 0;
        let minDist = Infinity;

        for (let i = 0; i < unvisited.length; i++) {
            let pLat = parseFloat(unvisited[i].latitude || currentLat);
            let pLng = parseFloat(unvisited[i].longitude || currentLng);
            let dist = calcularDistanciaLocal(currentLat, currentLng, pLat, pLng);
            
            if (dist < minDist) {
                minDist = dist;
                nearestIdx = i;
            }
        }

        let nearest = unvisited[nearestIdx];
        
        const row = document.querySelector(`#lista-rotas tr[data-id="${nearest.pedido_id}"]`);
        if (row) {
            document.getElementById('lista-rotas').appendChild(row);
        }

        order++;
        currentLat = parseFloat(nearest.latitude || currentLat);
        currentLng = parseFloat(nearest.longitude || currentLng);
        
        unvisited.splice(nearestIdx, 1);
    }
    
    atualizarNumerosOrdem();
    alert('📍 Ordem calculada por proximidade! Revise e clique em "Salvar Ordem" para confirmar.');
}

function atualizarNumerosOrdem() {
    const rows = document.querySelectorAll('#lista-rotas tr');
    rows.forEach((row, index) => {
        const numSpan = row.querySelector('.ordem-numero');
        if (numSpan) {
            numSpan.innerText = index + 1;
        }
    });
}

async function salvarOrdemRotas() {
    const rows = document.querySelectorAll('#lista-rotas tr');
    const dados = [];
    rows.forEach((row, index) => {
        if (row.getAttribute('data-id')) {
            dados.push({
                id: row.getAttribute('data-id'),
                ordem: index + 1
            });
        }
    });

    if (dados.length === 0) return;

    try {
        const res = await fetch('api_admin_ordem_rotas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        const json = await res.json();
        if (json.success) {
            alert('Ordem atualizada com sucesso!');
            carregarRotas();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch (e) {
        alert('Erro ao salvar ordem.');
    }
}

let modoGerarKit = 'gerar';

async function abrirModalGerarPedidos(modo = 'gerar') {
    modoGerarKit = modo;
    document.getElementById('modalGerarPedidos').style.display = 'flex';
    document.getElementById('modalGerarTitle').innerText = modo === 'editar' ? 'Editar Pedidos da Semana (Desfaz e Recria)' : 'Gerar Pedidos da Semana';
    document.getElementById('btnConfirmarGerarPedidos').innerText = modo === 'editar' ? 'Confirmar Edição (Re-gerar)' : 'Criar Pedidos em Lote';
    
    const container = document.getElementById('lista-produtos-gerar');
    container.innerHTML = '<span style="color:#888;">Carregando produtos...</span>';
    try {
        const res = await fetch('api_admin_produtos_v2.php');
        const json = await res.json();
        if (json.success) {
            container.innerHTML = '';
            json.data.forEach(p => {
                if(p.estoque_atual > 0) {
                    let estoqueFloat = parseFloat(p.estoque_atual);
                    let unit = p.tipo_venda === 'Fracionado' ? 'g' : p.unidade;
                    let isUnSelected = p.unidade === 'un' || p.unidade === 'unidade' || p.tipo_venda === 'Inteiro';
                    let isGSelected = p.tipo_venda === 'Fracionado';
                    container.innerHTML += `
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:5px; font-size:0.9em; margin-bottom:5px;">
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer; flex:1;">
                                <input type="checkbox" class="chk-prod-gerar" value="${p.id}" data-nome="${escapeHTML(p.nome)}" data-unidade="${escapeHTML(p.unidade)}" data-tipovenda="${escapeHTML(p.tipo_venda)}">
                                <span>${escapeHTML(p.nome)} (Estoque: ${estoqueFloat} ${unit})</span>
                            </label>
                            <div style="display:flex; gap: 5px;">
                                <input type="number" id="qtd-prod-${p.id}" value="1" min="1" max="9999" style="width:60px; padding:2px 5px; font-size:0.9em;" title="Quantidade">
                                <select id="uni-prod-${p.id}" style="padding:2px 5px; font-size:0.9em; width: 80px;">
                                    <option value="un" ${isUnSelected && !isGSelected ? 'selected' : ''}>unidade(s)</option>
                                    <option value="g" ${isGSelected ? 'selected' : ''}>g</option>
                                    <option value="kg">kg</option>
                                    <option value="maço">maço(s)</option>
                                    <option value="pé">pé(s)</option>
                                </select>
                            </div>
                        </div>
                    `;
                }
            });
        }
    } catch(e) {
        container.innerHTML = '<span style="color:red">Erro ao carregar produtos.</span>';
    }
}

async function confirmarGerarPedidos() {
    const selecionados = Array.from(document.querySelectorAll('.chk-prod-gerar:checked')).map(cb => {
        let qtd = parseInt(document.getElementById('qtd-prod-' + cb.value).value) || 1;
        let unidade_escolhida = document.getElementById('uni-prod-' + cb.value).value;
        let tipoVenda = cb.getAttribute('data-tipovenda') || 'Inteiro';
        let nome = cb.getAttribute('data-nome');
        
        let textoApresentacao = "";
        if (unidade_escolhida === 'g') {
            textoApresentacao = `${qtd}g de ${nome}`;
        } else if (unidade_escolhida === 'kg') {
            textoApresentacao = `${qtd}kg de ${nome}`;
        } else {
            let descUnidade = unidade_escolhida;
            if (qtd > 1 && !descUnidade.endsWith('s')) {
                if (descUnidade.toLowerCase() === 'pé') descUnidade = 'pés';
                else if (descUnidade.toLowerCase() === 'maço') descUnidade = 'maços';
                else if (descUnidade.toLowerCase() === 'un' || descUnidade.toLowerCase() === 'unidade') descUnidade = 'unidades';
                else if (descUnidade.toLowerCase() !== 'un') descUnidade += 's';
            }
            if (descUnidade === 'un' && qtd === 1) descUnidade = 'unidade';
            textoApresentacao = `${qtd} ${descUnidade} de ${nome}`;
        }

        return { id: cb.value, nome: nome, quantidade: qtd, textoApresentacao: textoApresentacao, unidade_escolhida: unidade_escolhida };
    });

    if (selecionados.length === 0) {
        return alert('Selecione pelo menos um produto para compor o Kit da Semana.');
    }

    let mensagemConfirmacao = modoGerarKit === 'editar' 
        ? 'Isso irá CANCELAR E DELETAR todos os pedidos de assinatura "Em separação" gerados nesta semana (devolvendo os estoques), e recriá-los com esta nova lista. Deseja continuar?'
        : 'Deseja realmente gerar os pedidos em lote para todos os assinantes ativos com esses itens e quantidades?';

    if (!confirm(mensagemConfirmacao)) return;

    // Atualiza automaticamente o texto do Kit da Semana
    const kitText = selecionados.map(s => s.textoApresentacao).join(', ');
    try {
        await fetch('api_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kit_semana: kitText })
        });
        if(document.getElementById('config-kit-semana')) {
            document.getElementById('config-kit-semana').value = kitText;
        }
    } catch(e) { console.error('Erro ao salvar kit_semana automaticamente:', e); }

    const btn = document.getElementById('btnConfirmarGerarPedidos');
    btn.innerText = 'Processando...';
    btn.disabled = true;

    const apiUrl = modoGerarKit === 'editar' ? 'api_admin_editar_kit_semana.php' : 'api_admin_gerar_pedidos_v2.php';

    try {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ produtos_kit: selecionados })
        });
        const json = await res.json();
        if (json.success) {
            let msg = modoGerarKit === 'editar'
                ? `Kit da semana editado com sucesso! ${json.removidos} pedidos desfeitos e ${json.gerados} novos gerados.\n\nO texto visível aos clientes também foi atualizado.`
                : `Pedidos gerados com sucesso! Foram criados ${json.gerados} pedidos.\n\nO texto do "Kit da Semana" visível aos clientes também foi atualizado automaticamente.`;
            alert(msg);
            fecharModal('modalGerarPedidos');
            carregarPedidos();
            carregarDashboardCounts();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch(e) {
        alert('Erro de conexão ao gerar/editar pedidos.');
    } finally {
        btn.innerText = modoGerarKit === 'editar' ? 'Confirmar Edição (Re-gerar)' : 'Criar Pedidos em Lote';
        btn.disabled = false;
    }
}

async function notificarAssinantes() {
    if (!confirm('Deseja gerar os links de WhatsApp e notificar todos os assinantes ativos sobre as opções desta semana?')) return;
    try {
        const res = await fetch('api_admin_clientes_v2.php');
        const json = await res.json();
        if (json.success) {
            let ativos = json.data.filter(c => c.status === 'Ativa');
            if (ativos.length === 0) return alert('Nenhum assinante ativo para notificar.');
            
            // Simulação de disparo: gerar links
            let wnd = window.open('', '_blank');
            wnd.document.write('<h2>Links Rápidos - Disparo WhatsApp</h2><p>Clique em cada link para enviar a notificação no WhatsApp Web/App:</p><ul>');
            ativos.forEach(c => {
                let msg = encodeURIComponent(`Olá ${c.nome}, a Faz Bem já selecionou os produtos básicos do seu kit dessa semana! As opções para pedidos extras também já estão liberadas. Confira no site!`);
                let num = c.telefone.replace(/\\D/g, '');
                wnd.document.write(`<li><a href="https://wa.me/55${num}?text=${msg}" target="_blank">${c.nome} - Enviar Mensagem</a></li>`);
            });
            wnd.document.write('</ul>');
        }
    } catch(e) {
        alert('Erro ao processar notificações.');
    }
}

async function carregarFaturasAdmin() {
    const tbody = document.getElementById('lista-faturas-admin');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Carregando...</td></tr>';
    try {
        const res = await fetch('api_faturamento_v2.php?acao=listar_faturas_admin');
        const json = await res.json();
        
        if (json.success) {
            tbody.innerHTML = '';
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Nenhuma fatura encontrada.</td></tr>';
                return;
            }
            
            json.data.forEach(f => {
                let badgeClass = f.status === 'Pago' ? 'st-pago' : 'st-pendente';
                let bgBadge = f.status === 'Pago' ? '#dcfce7' : '#fee2e2';
                let colBadge = f.status === 'Pago' ? '#166534' : '#991b1b';
                
                let statusDisplay = `<span style="background:${bgBadge}; color:${colBadge}; padding:4px 8px; border-radius:12px; font-size:12px; font-weight:bold">${f.status}</span>`;
                if (f.status === 'Pago') {
                    statusDisplay += `<br><a href="comprovante.php?tipo=fatura&id=${f.id}" target="_blank" style="font-size:11px; text-decoration:underline; color:#1d4ed8; display:inline-block; margin-top:4px;">Ver Recibo</a>`;
                }

                tbody.innerHTML += `<tr>
                    <td>#${f.id}</td>
                    <td>${escapeHTML(f.mes_referencia)}</td>
                    <td><strong>${escapeHTML(f.cliente)}</strong></td>
                    <td>R$ ${parseFloat(f.valor_mensalidade).toFixed(2).replace('.', ',')}</td>
                    <td>R$ ${parseFloat(f.valor_extras).toFixed(2).replace('.', ',')}</td>
                    <td style="color:#b45309">- R$ ${parseFloat(f.valor_desconto_creditos).toFixed(2).replace('.', ',')}</td>
                    <td style="font-weight:bold; color:#16a34a">R$ ${parseFloat(f.valor_total).toFixed(2).replace('.', ',')}</td>
                    <td>${statusDisplay}</td>
                </tr>`;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Erro ao carregar faturas: ' + json.message + '</td></tr>';
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">Erro de conexão.</td></tr>';
    }
}

async function gerarFaturasDoMes() {
    if (!confirm('Deseja realmente gerar as faturas do mês atual para todos os assinantes ativos/pausados? Esta ação é irreversível.')) return;
    try {
        const res = await fetch('api_faturamento_v2.php?acao=gerar_faturas', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            alert(json.message);
            carregarFaturasAdmin();
            carregarDashboardCounts();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch(e) {
        alert('Erro de conexão ao gerar faturas.');
    }
}