const MAPA_DEFAULT_LAT = -29.7614; // Default ( São Paulo)
const MAPA_DEFAULT_LNG = -57.0853;

let map;
let markers = [];

document.addEventListener("DOMContentLoaded", () => {
    initMap();
    carregarEntregas();
});

function initMap() {
    map = L.map('map-container').setView([MAPA_DEFAULT_LAT, MAPA_DEFAULT_LNG], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
}

async function carregarEntregas() {
    try {
        const res = await fetch('api_logistica_v2.php');
        const json = await res.json();
        const container = document.getElementById('entregas-list');

        if (json.success) {
            container.innerHTML = '';
            limparMarkers();

            if (json.data.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#6b7280;">Nenhuma entrega pendente.</p>';
                return;
            }

            let bounds = [];

            json.data.forEach((e, idx) => {
                const badgeClass = e.status_entrega === 'Saiu para entrega' ? 'badge saiu' : 'badge';
                const foneNum = e.telefone.replace(/\D/g, ''); // limpa formatação
                const msgWpp = encodeURIComponent(`Olá ${e.nome}, seu pedido da FazBem saiu para entrega e está a caminho! 🚚🌿`);
                const wppLink = `https://wa.me/55${foneNum}?text=${msgWpp}`;

                // Usa lat/lng reais se houver, senão usa o padrão
                let lat = parseFloat(e.latitude);
                let lng = parseFloat(e.longitude);
                if (isNaN(lat) || isNaN(lng)) {
                    lat = MAPA_DEFAULT_LAT;
                    lng = MAPA_DEFAULT_LNG;
                }

                bounds.push([lat, lng]);

                const marker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(`<b>${e.nome}</b><br>${e.logradouro}`);
                markers.push(marker);

                let btnAction = '';
                if (e.status_entrega === 'Em separação') {
                    btnAction = `<button class="btn btn-action" onclick="atualizarStatus(${e.pedido_id}, 'Saiu para entrega')">📍 Iniciar Rota</button>`;
                } else if (e.status_entrega === 'Saiu para entrega') {
                    btnAction = `<button class="btn btn-action" onclick="atualizarStatus(${e.pedido_id}, 'Entregue')">✅ Finalizar</button>`;
                }

                // Adicionar na Lista
                container.innerHTML += `
                    <div class="entrega-card">
                        <div class="entrega-header">
                            <strong>#${e.pedido_id} - ${e.nome}</strong>
                            <span class="${badgeClass}">${e.status_entrega}</span>
                        </div>
                        <div style="font-size: 14px; margin-bottom: 5px;">📍 ${e.logradouro}</div>
                        <div style="font-size: 13px; color: #6b7280;">📞 ${e.telefone}</div>
                        <div class="actions">
                            <a href="${wppLink}" target="_blank" class="btn btn-wpp">💬 Avisar Cliente</a>
                            ${btnAction}
                        </div>
                    </div>
                `;
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [20, 20] });
            }

        } else {
            container.innerHTML = '<p>Erro ao carregar roteiro.</p>';
        }
    } catch (err) {
        console.error(err);
    }
}

function limparMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
}

async function atualizarStatus(pedido_id, novo_status) {
    if (!confirm(`Confirmar mudança de status para: ${novo_status}?`)) return;
    try {
        const res = await fetch('api_logistica_v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pedido_id: pedido_id, status: novo_status })
        });
        const json = await res.json();
        if (json.success) {
            carregarEntregas();
        } else {
            alert('Falha ao atualizar.' + (json.message ? " " + json.message : ""));
        }
    } catch (err) {
        alert('Erro ao conectar com servidor.');
    }
}
