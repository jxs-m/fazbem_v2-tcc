const MAPA_DEFAULT_LAT = -29.7603; // Uruguaiana
const MAPA_DEFAULT_LNG = -57.0811;

let map;
let markers = [];
let activeRouteDest = null;
let driverLatLng = null;
let routeLine = null;

if (typeof escapeHTML === 'undefined') {
    window.escapeHTML = function (str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };
}

document.addEventListener("DOMContentLoaded", () => {
    initMap();
    carregarEntregas();
    initGPS();
});

function initMap() {
    map = L.map('map-container', {
        maxBounds: [
            [-30.05, -57.45],
            [-29.45, -56.85]
        ],
        maxBoundsViscosity: 1.0
    }).setView([MAPA_DEFAULT_LAT, MAPA_DEFAULT_LNG], 14);
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

            activeRouteDest = null; // Reinicia a rota ativa

            if (json.data.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#6b7280;">Nenhuma entrega pendente.</p>';
                limparRota();
                return;
            }

            let bounds = [];

            json.data.forEach((e, idx) => {
                const badgeClass = e.status_entrega === 'Saiu para entrega' ? 'badge saiu' : 'badge';
                const foneNum = e.telefone.replace(/\D/g, ''); // limpa formatação
                const msgWpp = encodeURIComponent(`Olá, estou chegando com sua entrega!`);
                const wppLink = `https://wa.me/55${foneNum}?text=${msgWpp}`;

                // Usa lat/lng reais se houver, senão usa o padrão
                let lat = parseFloat(e.latitude);
                let lng = parseFloat(e.longitude);
                const hasValidCoords = !isNaN(lat) && !isNaN(lng);

                if (!hasValidCoords) {
                    lat = MAPA_DEFAULT_LAT;
                    lng = MAPA_DEFAULT_LNG;
                }

                bounds.push([lat, lng]);

                const marker = L.marker([lat, lng]).addTo(map)
                    .bindPopup(`<b>${escapeHTML(e.nome)}</b><br>${escapeHTML(e.logradouro)}`);
                markers.push(marker);

                let btnAction = '';
                let btnMaps = '';

                if (e.status_entrega === 'Aguardando Entrega' || e.status_entrega === 'Em separação') {
                    btnAction = `<button class="btn btn-action" onclick="atualizarStatus(${e.pedido_id}, 'Saiu para entrega')">📍 Iniciar Rota</button>`;
                } else if (e.status_entrega === 'Saiu para entrega') {
                    btnAction = `<button class="btn btn-action" onclick="atualizarStatus(${e.pedido_id}, 'Entregue')">✅ Finalizar</button>`;

                    if (hasValidCoords) {
                        activeRouteDest = { lat, lng };
                        btnMaps = `<a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" target="_blank" class="btn btn-maps">🗺️ Navegar</a>`;
                    }
                }

                container.innerHTML += `
                    <div class="entrega-card">
                        <div class="entrega-header">
                            <strong>#${e.pedido_id} - ${escapeHTML(e.nome)}</strong>
                            <span class="${badgeClass}">${escapeHTML(e.status_entrega)}</span>
                        </div>
                        <div style="font-size: 14px; margin-bottom: 5px;">📍 ${escapeHTML(e.logradouro)}</div>
                        <div style="font-size: 13px; color: #6b7280;">📞 ${escapeHTML(e.telefone)}</div>
                        <div class="actions">
                            <a href="${escapeHTML(wppLink)}" target="_blank" class="btn btn-wpp">💬 Avisar Cliente</a>
                            ${btnMaps}
                            ${btnAction}
                        </div>
                    </div>
                `;
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [20, 20] });
            }

            if (!activeRouteDest) {
                limparRota();
            } else if (driverLatLng) {
                tracaRota(driverLatLng.lat, driverLatLng.lng, activeRouteDest.lat, activeRouteDest.lng);
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

let gpsWatchId = null;
let gpsMarker = null;

function initGPS() {
    const gpsBtn = document.getElementById('gps-btn');
    if (!gpsBtn) return;

    gpsBtn.addEventListener('click', () => {
        if (gpsWatchId === null) {
            // Ativa o rastreamento do GPS
            if ("geolocation" in navigator) {
                gpsBtn.classList.add('tracking');
                gpsBtn.textContent = '📡';

                // Remove restrições de limite do mapa para fins de teste
                map.setMaxBounds(null);

                gpsWatchId = navigator.geolocation.watchPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        driverLatLng = { lat, lng };

                        // Adiciona ou move o marcador do entregador no mapa
                        if (gpsMarker) {
                            gpsMarker.setLatLng([lat, lng]);
                        } else {
                            const pulseIcon = L.divIcon({
                                className: 'gps-marker-icon',
                                html: '<div class="gps-marker-pulse"></div>',
                                iconSize: [14, 14],
                                iconAnchor: [7, 7]
                            });
                            gpsMarker = L.marker([lat, lng], { icon: pulseIcon }).addTo(map)
                                .bindPopup("Você está aqui");
                        }

                        // Se houver uma rota ativa, traça
                        if (activeRouteDest) {
                            tracaRota(lat, lng, activeRouteDest.lat, activeRouteDest.lng);
                        } else {
                            // Centraliza o mapa na posição do entregador se não houver rota ativa
                            map.setView([lat, lng], 16);
                        }
                    },
                    (error) => {
                        console.error("Erro no GPS: ", error);
                        alert("Não foi possível obter sua localização. Verifique as permissões de GPS.");
                        desativarGPS();
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 5000,
                        timeout: 10000
                    }
                );
            } else {
                alert("Seu navegador não suporta Geolocalização.");
            }
        } else {
            // Desativa o rastreamento
            desativarGPS();
        }
    });
}

function desativarGPS() {
    const gpsBtn = document.getElementById('gps-btn');
    if (gpsBtn) {
        gpsBtn.classList.remove('tracking');
        gpsBtn.textContent = '🎯';
    }
    if (gpsWatchId !== null) {
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }
    if (gpsMarker) {
        map.removeLayer(gpsMarker);
        gpsMarker = null;
    }
    driverLatLng = null;
    limparRota();

    // Restaura maxBounds originais de Uruguaiana
    map.setMaxBounds([
        [-30.05, -57.45],
        [-29.45, -56.85]
    ]);
}

async function tracaRota(startLat, startLng, endLat, endLng) {
    if (!startLat || !startLng || !endLat || !endLng) return;
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.routes && json.routes.length > 0) {
            const coordinates = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);

            if (routeLine) {
                routeLine.setLatLngs(coordinates);
            } else {
                routeLine = L.polyline(coordinates, {
                    color: '#3b82f6',
                    weight: 6,
                    opacity: 0.85,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);
            }

            map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });
        }
    } catch (error) {
        console.error("Erro ao traçar rota: ", error);
    }
}

function limparRota() {
    if (routeLine) {
        map.removeLayer(routeLine);
        routeLine = null;
    }
}
