let pedidosSeparacao = [];
let pedidoAtual = null;

function getBaseGrams(unidadeStr) {
    let u = (unidadeStr || '').toLowerCase();
    if (u.includes('kg')) return 1000;
    if (u.includes('g') && !u.includes('kg')) {
       let num = parseInt(u);
       if (!isNaN(num) && num > 0) return num;
    }
    return null;
}

async function carregarPedidosSeparacao() {
    const container = document.getElementById('lista-pedidos');
    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #666;">Carregando pedidos...</div>';

    try {
        const res = await fetch('api_separacao_v2.php');
        const json = await res.json();

        if (json.success) {
            pedidosSeparacao = json.pedidos;
            container.innerHTML = '';

            if (pedidosSeparacao.length === 0) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #666; padding: 40px; background: white; border-radius: 8px;">Nenhum pedido na fila de separação. 🎉</div>';
                return;
            }

            pedidosSeparacao.forEach(p => {
                let d = new Date(p.data_pedido).toLocaleString('pt-BR');
                let obsHtml = p.obs_pontual ? `<p style="color:#b45309; font-weight:bold;">⚠️ Contém Observações</p>` : '';
                
                container.innerHTML += `
                    <div class="card-pedido">
                        <div>
                            <h3>Pedido #${p.id}</h3>
                            <p><strong>Cliente:</strong> ${escapeHTML(p.cliente)}</p>
                            <p><strong>Data:</strong> ${d}</p>
                            <p><strong>Itens:</strong> ${p.itens.length}</p>
                            ${obsHtml}
                        </div>
                        <button class="btn btn-primary btn-iniciar" onclick="abrirSeparacao(${p.id})">Iniciar Separação / Pesagem</button>
                    </div>
                `;
            });
        } else {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: red;">${escapeHTML(json.message)}</div>`;
        }
    } catch (e) {
        container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: red;">Erro ao carregar fila.</div>`;
    }
}

function abrirSeparacao(id) {
    pedidoAtual = pedidosSeparacao.find(p => p.id === id);
    if (!pedidoAtual) return;

    document.getElementById('modalSeparacao').style.display = 'flex';
    document.getElementById('sep-pedido-id').innerText = pedidoAtual.id;
    document.getElementById('sep-cliente-nome').innerText = pedidoAtual.cliente;

    const divObs = document.getElementById('sep-obs');
    if (pedidoAtual.obs_pontual) {
        divObs.style.display = 'block';
        divObs.innerHTML = '<strong>OBS do Cliente:</strong> ' + escapeHTML(pedidoAtual.obs_pontual);
    } else {
        divObs.style.display = 'none';
    }

    // Renderiza as preferências do cliente se houverem cadastradas
    const divExcecoes = document.getElementById('sep-excecoes');
    const pExcecoesTexto = document.getElementById('sep-excecoes-texto');

    if (pedidoAtual.preferencias && pedidoAtual.preferencias.length > 0) {
        divExcecoes.style.display = 'block';
        pExcecoesTexto.innerHTML = pedidoAtual.preferencias.map(pref => {
            return `• <strong>${escapeHTML(pref.tipo)}</strong>: ${escapeHTML(pref.descricao)}`;
        }).join('<br>');
    } else {
        divExcecoes.style.display = 'none';
    }

    const containerItens = document.getElementById('sep-itens');
    containerItens.innerHTML = '';

    pedidoAtual.itens.forEach((item, idx) => {
        let baseGrams = getBaseGrams(item.unidade);
        let pesoEstimadoG = parseFloat(item.peso_estimado_g) || 0;
        let quantidadeDB = parseFloat(item.quantidade);
        
        let labelDisplay = '';
        let hintDisplay = '';
        let step = 'any';
        
        let valSugerido = quantidadeDB;
        let unitLabel = item.unidade;
        let placeholder = `Ex: ${quantidadeDB}`;
        
        if (baseGrams !== null) {
            valSugerido = Math.round(quantidadeDB * baseGrams);
            unitLabel = 'g';
            placeholder = `Ex: ${valSugerido}`;
        }
        
        if (item.tipo_venda === 'Fracionado') {
            // Cliente pediu em gramas
            let grams = (baseGrams !== null) ? Math.round(quantidadeDB * baseGrams) : quantidadeDB;
            labelDisplay = `<strong>${grams}g</strong> de ${escapeHTML(item.nome)}`;
            hintDisplay = `Vendido por: ${item.unidade} | Insira o peso real em gramas`;
        } else {
            // Cliente pediu em unidades inteiras
            let units = 0;
            if (baseGrams !== null && pesoEstimadoG > 0) {
                units = Math.round((quantidadeDB * baseGrams) / pesoEstimadoG);
            } else {
                units = quantidadeDB;
            }
            
            labelDisplay = `<strong>${units} un</strong> de ${escapeHTML(item.nome)}`;
            if (baseGrams !== null) {
                // Vendido a peso, mas pedido em unidade (ex: Banana)
                let expectedGrams = Math.round(quantidadeDB * baseGrams);
                hintDisplay = `Separar ${units} unidades e <strong>pesar</strong> (esperado ~${expectedGrams}g)`;
            } else {
                // Vendido em unidade (ex: Alface)
                hintDisplay = `Vendido por: ${item.unidade} | Apenas confirme a quantidade`;
            }
        }

        containerItens.innerHTML += `
            <div class="item-separacao" id="item-sep-box-${item.item_id}">
                <div class="item-header">
                    <div class="item-nome" style="font-size: 16px;">${labelDisplay}</div>
                    <div class="item-qtd-pedida" style="color:#b45309; font-weight:500;">${hintDisplay}</div>
                </div>
                <div class="item-actions" style="margin-top: 10px; background: #f9fafb; padding: 10px; border-radius: 6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label style="font-size:14px; font-weight:bold; color:#374151;">Valor Real (${unitLabel}):</label>
                        <input type="number" id="input-real-${item.item_id}" class="input-peso" step="${step}" value="${valSugerido}" placeholder="${placeholder}" oninput="limparFalta(${item.item_id})" style="border-color:#16a34a; background:#f0fdf4;">
                    </div>
                    <button class="btn-falta" id="btn-falta-${item.item_id}" onclick="marcarFalta(${item.item_id})">Marcar em Falta</button>
                </div>
            </div>
        `;
    });
}

function marcarFalta(itemId) {
    let box = document.getElementById('item-sep-box-' + itemId);
    let btn = document.getElementById('btn-falta-' + itemId);
    let input = document.getElementById('input-real-' + itemId);

    if (box.classList.contains('falta')) {
        // Desmarca
        box.classList.remove('falta');
        btn.classList.remove('ativo');
        btn.innerText = 'Marcar em Falta';
        input.disabled = false;
        // Tenta recuperar do array original
        let it = pedidoAtual.itens.find(i => i.item_id == itemId);
        if (it) {
            let baseGrams = getBaseGrams(it.unidade);
            input.value = baseGrams !== null ? Math.round(parseFloat(it.quantidade) * baseGrams) : it.quantidade;
        }
    } else {
        // Marca
        box.classList.add('falta');
        btn.classList.add('ativo');
        btn.innerText = 'Em Falta';
        input.value = 0;
        input.disabled = true;
    }
}

function limparFalta(itemId) {
    let box = document.getElementById('item-sep-box-' + itemId);
    if (box.classList.contains('falta')) {
        marcarFalta(itemId); // faz toggle
    }
}

function fecharModal(id) {
    document.getElementById(id).style.display = 'none';
    pedidoAtual = null;
}

async function finalizarSeparacao() {
    if (!pedidoAtual) return;

    if (!confirm('Finalizar separação deste pedido? O valor final da fatura será calculado com base nestas quantidades.')) return;

    let payload = {
        pedido_id: pedidoAtual.id,
        itens: []
    };

    let allOk = true;

    pedidoAtual.itens.forEach(item => {
        let input = document.getElementById('input-real-' + item.item_id);
        let val = parseFloat(input.value);

        if (isNaN(val) || val < 0) {
            allOk = false;
        }

        // Se o item é vendido por peso (baseGrams não é nulo), o operador digitou o peso real em gramas.
        // Precisamos converter de volta para a unidade do banco (ex: dividir por 1000 se for kg).
        let baseGrams = getBaseGrams(item.unidade);
        let q_real = val;
        if (baseGrams !== null) {
            q_real = val / baseGrams;
        }

        payload.itens.push({
            item_id: item.item_id,
            quantidade_real: q_real
        });
    });

    if (!allOk) {
        return alert("Há itens com valores inválidos. Revise antes de finalizar.");
    }

    const btn = document.getElementById('btnFinalizarSeparacao');
    btn.innerText = 'Salvando...';
    btn.disabled = true;

    try {
        const res = await fetch('api_separacao_v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            alert('Separação concluída! O pedido foi liberado para entrega.');
            fecharModal('modalSeparacao');
            carregarPedidosSeparacao();
        } else {
            alert('Erro: ' + json.message);
        }
    } catch (e) {
        alert('Erro ao enviar dados.');
    } finally {
        btn.innerText = '✔ Finalizar e Liberar Entrega';
        btn.disabled = false;
    }
}

// Inicializar
carregarPedidosSeparacao();
