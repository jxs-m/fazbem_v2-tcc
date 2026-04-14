document.addEventListener('DOMContentLoaded', () => {
      carregarPerfil();
      carregarPedidos();
    });

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
        lista.innerHTML = '<li><span style="color:#999;">Nenhuma preferência fixa definida.</span></li>';

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
            tbody.innerHTML += `<tr><td><div style="font-weight:bold">${new Date(p.data_pedido).toLocaleDateString('pt-BR')}</div><span class="tag ${tagClass}">${escapeHTML(p.status_entrega)}</span></td><td style="text-align:right"><div style="font-weight:bold; color:#2b8a3e">R$ ${parseFloat(p.valor_total).toFixed(2).replace('.', ',')}</div><div style="font-size:12px">${escapeHTML(p.status_pagamento)}</div></td></tr>`;
          });
        }
      } catch (e) { }
    }

    async function alterarStatus(acao) {
      if (!confirm('Confirmar ação?')) return;
      try {
        await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: acao }) });
        carregarPerfil();
      } catch (e) { alert('Erro'); }
    }

    async function salvarPreferencia() {
      const desc = document.getElementById('nova-pref').value;
      if (!desc) return;
      try {
        await fetch('api_gerenciar_assinatura_v2.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao: 'nova_preferencia', tipo: 'Troca Fixa', descricao: desc }) });
        document.getElementById('nova-pref').value = '';
        carregarPerfil();
      } catch (e) { alert('Erro'); }
    }