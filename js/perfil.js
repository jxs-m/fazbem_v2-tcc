document.addEventListener('DOMContentLoaded', () => {
      carregarPerfil();
      carregarPedidos();
      carregarFaturas();
      carregarPedidoSemana();
    });

    function getBaseGrams(unidadeStr) {
        let u = (unidadeStr || '').toLowerCase();
        if (u.includes('kg')) return 1000;
        if (u.includes('g') && !u.includes('kg')) {
           let num = parseInt(u);
           if (!isNaN(num) && num > 0) return num;
        }
        return null;
    }

    async function carregarPedidoSemana() {
        const card = document.getElementById('card-pedido-semana');
        const statusSpan = document.getElementById('pedido-semana-status');
        const detalhesDiv = document.getElementById('pedido-semana-detalhes');
        const itensDiv = document.getElementById('pedido-semana-itens');
        const valoresDiv = document.getElementById('pedido-semana-valores');

        if (!card) return;

        try {
            const res = await fetch('api_meus_pedidos_v2.php?acao=pedido_semana');
            const json = await res.json();

            if (json.success && json.pedido) {
                const p = json.pedido;
                card.style.display = 'block';

                let statusBg = '#dbeafe';
                let statusColor = '#1e40af';
                if (p.status_entrega === 'Entregue') {
                    statusBg = '#dcfce7';
                    statusColor = '#166534';
                } else if (p.status_entrega === 'Saiu para entrega') {
                    statusBg = '#fef9c3';
                    statusColor = '#854d0e';
                } else if (p.status_entrega === 'Aguardando Entrega') {
                    statusBg = '#ffedd5';
                    statusColor = '#c2410c';
                }

                statusSpan.innerText = p.status_entrega;
                statusSpan.style.background = statusBg;
                statusSpan.style.color = statusColor;

                const dataF = new Date(p.data_pedido).toLocaleDateString('pt-BR');
                detalhesDiv.innerHTML = `Pedido #${p.id} gerado em ${dataF}.<br>Tipo: <strong>${p.tipo_pedido}</strong>`;

                itensDiv.innerHTML = '';
                let totalSoma = 0;
                let temPesagem = false;

                json.itens.forEach(item => {
                    let baseGrams = getBaseGrams(item.unidade);
                    let qtyDisplay = '';
                    let subtotal = 0;

                    let isWeighed = item.quantidade_real !== null;
                    if (isWeighed) {
                        temPesagem = true;
                    }

                    if (item.tipo_venda === 'Fracionado') {
                        let qtyRequestedG = baseGrams !== null ? Math.round(parseFloat(item.quantidade) * baseGrams) : item.quantidade;
                        qtyDisplay = `Solicitado: ${qtyRequestedG}g`;
                        
                        if (isWeighed) {
                            let qtyRealG = baseGrams !== null ? Math.round(parseFloat(item.quantidade_real) * baseGrams) : item.quantidade_real;
                            qtyDisplay += ` ➔ <strong>Pesado: ${qtyRealG}g</strong>`;
                            subtotal = parseFloat(item.preco_real || (item.quantidade_real * item.preco_unitario));
                        } else {
                            subtotal = parseFloat(item.quantidade * item.preco_unitario);
                        }
                    } else {
                        let qtyRequestedUn = baseGrams !== null && parseFloat(item.peso_estimado_g) > 0 
                            ? Math.round((parseFloat(item.quantidade) * baseGrams) / parseFloat(item.peso_estimado_g))
                            : parseFloat(item.quantidade);
                        qtyDisplay = `${qtyRequestedUn} ${item.unidade}`;

                        if (baseGrams !== null) {
                            let expectedG = Math.round(parseFloat(item.quantidade) * baseGrams);
                            qtyDisplay += ` (~${expectedG}g)`;

                            if (isWeighed) {
                                let qtyRealG = Math.round(parseFloat(item.quantidade_real) * baseGrams);
                                qtyDisplay += ` ➔ <strong>Pesado: ${qtyRealG}g</strong>`;
                                subtotal = parseFloat(item.preco_real || (item.quantidade_real * item.preco_unitario));
                            } else {
                                subtotal = parseFloat(item.quantidade * item.preco_unitario);
                            }
                        } else {
                            subtotal = parseFloat(item.quantidade * item.preco_unitario);
                        }
                    }

                    totalSoma += subtotal;
                    let precoUnit = parseFloat(item.preco_unitario).toFixed(2).replace('.', ',');
                    let subtotalF = subtotal.toFixed(2).replace('.', ',');

                    let itemDetailsHTML = '';
                    if (p.tipo_pedido === 'Assinatura') {
                        itemDetailsHTML = `
                            <div>
                                <strong>${escapeHTML(item.nome)}</strong><br>
                                <span style="font-size:11px; color:#6b7280;">Qtd: ${qtyDisplay}</span>
                            </div>
                            <div style="font-weight:600; color:#9ca3af; font-size:11px;">Incluso no Kit</div>
                        `;
                    } else {
                        itemDetailsHTML = `
                            <div>
                                <strong>${escapeHTML(item.nome)}</strong><br>
                                <span style="font-size:11px; color:#6b7280;">Qtd: ${qtyDisplay} (R$ ${precoUnit} / ${item.unidade})</span>
                            </div>
                            <div style="font-weight:600; color:#374151;">R$ ${subtotalF}</div>
                        `;
                    }

                    itensDiv.innerHTML += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:13px; color:#374151;">
                            ${itemDetailsHTML}
                        </div>
                    `;
                });

                if (itensDiv.innerHTML === '') {
                    itensDiv.innerHTML = '<div style="text-align:center; color:#9ca3af; padding:10px;">Nenhum item neste pedido.</div>';
                }

                let totalF = parseFloat(p.valor_total).toFixed(2).replace('.', ',');
                
                let legend = temPesagem 
                    ? `<span style="font-size:11px; color:#2563eb; font-weight:normal;">* Valores finais atualizados após pesagem</span>`
                    : `<span style="font-size:11px; color:#6b7280; font-weight:normal;">* Aguardando pesagem real</span>`;

                valoresDiv.innerHTML = `
                    ${legend}
                    <span>Total: R$ ${totalF}</span>
                `;
            } else {
                card.style.display = 'none';
            }
        } catch (e) {
            console.error("Erro ao carregar pedido da semana:", e);
        }
    }

    async function carregarPerfil() {
      try {
        const resPerfil = await fetch('api_perfil_v2.php');
        const jsonPerfil = await resPerfil.json();

        if (!jsonPerfil.success) {
          if (jsonPerfil.message && jsonPerfil.message.includes('negado')) {
            window.location.href = 'login.html';
          }
          return;
        }

        const u = jsonPerfil.usuario;
        document.getElementById('dados-pessoais').innerHTML = `<strong>${escapeHTML(u.nome)}</strong><br>${escapeHTML(u.email)}<br>${escapeHTML(u.endereco)}`;
        
        let saldo = parseFloat(u.saldo_compensacao || 0).toFixed(2).replace('.', ',');
        document.getElementById('saldo-carteira').innerText = `R$ ${saldo}`;

        const resAssinatura = await fetch('api_minha_assinatura_v2.php');
        const jsonAssinatura = await resAssinatura.json();

        const badge = document.getElementById('status-badge');
        const btnContainer = document.getElementById('btn-container');
        const selectPlano = document.getElementById('select-plano');

        if (jsonAssinatura.success && jsonAssinatura.data) {
          const ass = jsonAssinatura.data;
          selectPlano.value = ass.frequencia;

          badge.innerText = ass.status;
          badge.className = 'status-badge';

          if (ass.status === 'Ativa') {
            badge.classList.add('st-ativa');
            btnContainer.innerHTML = `<button class="btn btn-pause" onclick="alterarStatus('pausar')">⏸ Pausar Entregas</button>`;
          } else if (ass.status === 'Pausada') {
            badge.classList.add('st-pausada');
            btnContainer.innerHTML = `<button class="btn btn-resume" onclick="alterarStatus('reativar')">▶ Retomar Entregas</button>`;
          } else {
            badge.classList.add('st-cancelada');
            btnContainer.innerHTML = `<button class="btn btn-resume" onclick="alterarStatus('reativar')">Reassinar</button>`;
          }
        } else {
          badge.innerText = 'Sem Assinatura';
          badge.className = 'status-badge st-cancelada';
          btnContainer.innerHTML = '';
        }

        const lista = document.getElementById('lista-prefs');
        lista.innerHTML = '';
        if (jsonPerfil.preferencias && jsonPerfil.preferencias.length > 0) {
            jsonPerfil.preferencias.forEach(p => {
                const li = document.createElement('li');
                li.innerHTML = `<span style="flex: 1; display: flex; align-items: center;"><strong>${escapeHTML(p.tipo)}:</strong>&nbsp;${escapeHTML(p.descricao)}</span> <button style="flex: 0 0 auto; border: none; border-radius: 4px; cursor: pointer; padding: 4px 8px; font-size: 12px; font-weight: bold; background: #fee2e2; color: #dc2626;" onclick="removerPreferencia(${p.id})">X</button>`;
                lista.appendChild(li);
            });
        } else {
            lista.innerHTML = '<li><span style="color:#999;">Nenhuma preferência fixa definida.</span></li>';
        }

        try {
            const resConfig = await fetch('api_config.php');
            const jsonConfig = await resConfig.json();
            if (jsonConfig.success && jsonConfig.data.kit_semana) {
                document.getElementById('kit-semana-atual').innerText = jsonConfig.data.kit_semana;
            } else {
                document.getElementById('kit-semana-atual').innerText = "Aguardando atualização...";
            }
        } catch(e) { console.error(e); }

      } catch (e) {
        console.error("Erro ao carregar perfil:", e);
      }
    }

    async function mudarPlano() {
      const novoPlano = document.getElementById('select-plano').value;

      if (!confirm(`Deseja alterar seu plano para ${novoPlano}?`)) return;

      try {
        const res = await fetch('api_gerenciar_assinatura_v2.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ acao: 'alterar_plano', nova_frequencia: novoPlano })
        });
        const json = await res.json();
        alert(json.message);
        if (json.success) carregarPerfil();
      } catch (e) { alert('Erro de conexão'); }
    }


    async function carregarPedidos() {
      const tbody = document.getElementById('lista-pedidos');
      try {
        const res = await fetch('api_meus_pedidos_v2.php');
        const json = await res.json();
        if (json.success) {
          tbody.innerHTML = '';
          if (json.data.length === 0) { tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:20px; color:#999">Sem pedidos.</td></tr>'; return; }
          json.data.forEach(p => {
            let tagClass = p.status_entrega === 'Entregue' ? 'tag-green' : 'tag-yellow';
            let statusPagamento = escapeHTML(p.status_pagamento);
            if (p.status_pagamento === 'Pago') {
                statusPagamento = `<span style="color:#16a34a; font-weight:bold;">Pago</span> <a href="comprovante.php?tipo=pedido&id=${p.id}" target="_blank" style="font-size:11px; text-decoration:underline; color:#1d4ed8; display:block; margin-top:2px;">Recibo</a>`;
            }
            tbody.innerHTML += `<tr><td><div style="font-weight:bold">${new Date(p.data_pedido).toLocaleDateString('pt-BR')}</div><span class="tag ${tagClass}">${escapeHTML(p.status_entrega)}</span></td><td style="text-align:right"><div style="font-weight:bold; color:#2b8a3e">R$ ${parseFloat(p.valor_total).toFixed(2).replace('.', ',')}</div><div style="font-size:12px">${statusPagamento}</div></td></tr>`;
          });
        }
      } catch (e) { }
    }

    async function alterarStatus(acao) {
      if (!confirm('Confirmar ação?')) return;
      try {
        const res = await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: acao }) });
        const json = await res.json();
        if (json.success) {
            if (json.message) alert(json.message);
            carregarPerfil();
        } else {
            alert('Erro: ' + json.message);
        }
      } catch (e) { alert('Erro ao alterar status da assinatura.'); }
    }

    async function salvarPreferencia() {
      const desc = document.getElementById('nova-pref').value;
      if (!desc) return;
      try {
        const res = await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: 'nova_preferencia', tipo: 'Troca Fixa', descricao: desc }) });
        const json = await res.json();
        if (json.success) {
            document.getElementById('nova-pref').value = '';
            carregarPerfil();
        } else {
            alert('Erro: ' + json.message);
        }
      } catch (e) { alert('Erro ao salvar preferência.'); }
    }

    async function removerPreferencia(id) {
      if (!confirm('Deseja remover essa preferência?')) return;
      try {
        const res = await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: 'remover_preferencia', pref_id: id }) });
        const json = await res.json();
        if (json.success) {
            carregarPerfil();
        } else {
            alert('Erro: ' + json.message);
        }
      } catch (e) { alert('Erro ao remover preferência.'); }
    }

    async function salvarTrocaPontual() {
      const desc = document.getElementById('nova-troca-pontual').value;
      if (!desc) return;
      try {
        const res = await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: 'nova_preferencia', tipo: 'Troca Pontual', descricao: desc }) });
        const json = await res.json();
        if (json.success) {
            alert('Sua solicitação de troca pontual foi salva! A nossa equipe será notificada durante a montagem do seu kit.');
            document.getElementById('nova-troca-pontual').value = '';
            carregarPerfil();
        } else {
            alert('Erro: ' + json.message);
        }
      } catch (e) { alert('Erro ao solicitar troca.'); }
    }

    async function carregarFaturas() {
      const tbody = document.getElementById('lista-faturas');
      try {
        const res = await fetch('api_faturamento_v2.php?acao=minhas_faturas');
        const json = await res.json();
        if (json.success) {
          tbody.innerHTML = '';
          if (json.faturas.length === 0) { 
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#999">Nenhuma fatura encontrada.</td></tr>'; 
            return; 
          }
          json.faturas.forEach(f => {
            let totalF = parseFloat(f.valor_total).toFixed(2).replace('.', ',');
            let btnAction = f.status === 'Pago' 
                ? `<span style="color:#16a34a; font-weight:bold; display:block; margin-bottom:2px;">Pago</span><a href="comprovante.php?tipo=fatura&id=${f.id}" class="btn" style="background:#1d4ed8; color:white; padding:2px 6px; font-size:11px; text-decoration:none; border-radius:4px; display:inline-block;" target="_blank">Ver Recibo</a>` 
                : `<button class="btn btn-edit" style="background:#166534; color:white; padding:4px 8px;" onclick="abrirModalPagamento(${f.id}, ${f.valor_total})">Pagar Agora</button>`;
            
            tbody.innerHTML += `<tr>
                <td><strong>${escapeHTML(f.mes_referencia)}</strong><br><small>Vcto: Mensal</small></td>
                <td style="text-align:right; font-weight:bold; color:#2b8a3e">R$ ${totalF}</td>
                <td style="text-align:right">${btnAction}</td>
            </tr>`;
          });
        }
      } catch (e) { tbody.innerHTML = '<tr><td colspan="3">Erro ao carregar faturas.</td></tr>'; }
    }

    let mpPerfil = null;
    let bricksBuilderPerfil = null;
    let paymentBrickControllerPerfil = null;
    let faturaAtualId = null;

    async function initMercadoPagoPerfil() {
      try {
        const res = await fetch('api_mp_key.php');
        const json = await res.json();
        if (json.public_key) {
            mpPerfil = new MercadoPago(json.public_key, { locale: 'pt-BR' });
            bricksBuilderPerfil = mpPerfil.bricks();
        }
      } catch (e) { console.error('Erro ao carregar MP Key no Perfil', e); }
    }
    
    // Inicializar MP ao carregar a página
    initMercadoPagoPerfil();

    function fecharModalMP() {
        document.getElementById('mp-modal').style.display = 'none';
        if (paymentBrickControllerPerfil) {
            paymentBrickControllerPerfil.unmount();
            paymentBrickControllerPerfil = null;
        }
    }

    async function abrirModalPagamento(id, valor) {
        faturaAtualId = id;
        const modal = document.getElementById('mp-modal');
        if (!modal) {
            alert('Por favor, atualize a página completamente (Ctrl + F5 ou Limpar Cache do navegador). A nova janela de pagamento ainda não foi carregada no seu navegador!');
            return;
        }
        modal.style.display = 'flex';

        if (!bricksBuilderPerfil) {
            alert('Mercado Pago não inicializado corretamente.');
            return;
        }

        const settings = {
            initialization: { amount: parseFloat(valor) },
            customization: {
                paymentMethods: { creditCard: "all", debitCard: "all", pix: "all" }
            },
            callbacks: {
                onReady: () => { console.log('Brick is ready'); },
                onSubmit: ({ selectedPaymentMethod, formData }) => {
                    return new Promise((resolve, reject) => {
                        processarFaturaBackend(formData)
                            .then(resolve)
                            .catch(reject);
                    });
                },
                onError: (error) => { console.error(error); alert('Erro na interface de pagamento.'); }
            }
        };

        if (paymentBrickControllerPerfil) {
            paymentBrickControllerPerfil.unmount();
        }
        paymentBrickControllerPerfil = await bricksBuilderPerfil.create('payment', 'paymentBrick_container', settings);
    }

    async function processarFaturaBackend(formData) {
        try {
            const res = await fetch('api_faturamento_v2.php?acao=pagar_fatura', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    fatura_id: faturaAtualId,
                    mercado_pago_data: formData
                })
            });
            const json = await res.json();
            if (json.success) {
                alert('✅ Fatura paga com sucesso!');
                fecharModalMP();
                carregarFaturas();
            } else {
                alert('❌ Erro: ' + json.message);
                throw new Error(json.message);
            }
        } catch(e) { 
            console.error(e);
            throw e; 
        }
    }