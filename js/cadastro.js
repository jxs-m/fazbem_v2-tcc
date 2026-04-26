let map;
let marker;

document.addEventListener("DOMContentLoaded", () => {
  initMap();

  document.getElementById('btnBuscarMapa').addEventListener('click', buscarEnderecoNoMapa);
});

function initMap() {
  // Coordenada padrão

  const DEFAULT_LAT = -23.55052;
  const DEFAULT_LNG = -46.63330;

  map = L.map('map-cadastro').setView([DEFAULT_LAT, DEFAULT_LNG], 4);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  marker = L.marker([DEFAULT_LAT, DEFAULT_LNG], { draggable: true }).addTo(map);

  // Atualiza os inputs ocultos sempre que o pino for arrastado
  marker.on('dragend', function (e) {
    const posicao = marker.getLatLng();
    document.getElementById('latitude').value = posicao.lat;
    document.getElementById('longitude').value = posicao.lng;
  });
}

async function buscarEnderecoNoMapa() {
  const endereco = document.getElementById('endereco').value;
  if (!endereco) return alert("Por favor, digite o endereço primeiro.");

  const btn = document.getElementById('btnBuscarMapa');
  btn.innerText = "⏳...";
  btn.disabled = true;

  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(endereco)}`);
    const data = await res.json();

    if (data && data.length > 0) {
      const lat = parseFloat(data[0].lat);
      const lng = parseFloat(data[0].lon);

      // Move o mapa e o pino para as coordenadas
      map.setView([lat, lng], 16);
      marker.setLatLng([lat, lng]);

      // Atualiza inputs ocultos
      document.getElementById('latitude').value = lat;
      document.getElementById('longitude').value = lng;
    } else {
      alert("Endereço não localizado no mapa. Por favor, seja mais específico ou arraste o pino manualmente.");
    }
  } catch (err) {
    alert("Erro ao buscar no mapa.");
    console.error(err);
  } finally {
    btn.innerText = "🔍 Buscar";
    btn.disabled = false;
  }
}

document.getElementById('formCadastro').addEventListener('submit', async function (e) {
  e.preventDefault();

  const btn = document.getElementById('btnCadastrar');
  const originalText = btn.innerText;


  btn.innerText = "Criando conta...";
  btn.disabled = true;


  const lat = document.getElementById('latitude').value;
  const lng = document.getElementById('longitude').value;

  if (!lat || !lng) {
    alert('Por favor, clique em "🔍 Buscar no Mapa" ou arraste o pino para marcarmos a sua casa no sistema de rotas.');
    btn.innerText = originalText;
    btn.disabled = false;
    return;
  }

  const dados = {
    nome: document.getElementById('nome').value,
    email: document.getElementById('email').value,
    senha: document.getElementById('senha').value,
    cpf: document.getElementById('cpf').value,
    telefone: document.getElementById('telefone').value,
    endereco: document.getElementById('endereco').value,
    referencia: document.getElementById('referencia').value,
    frequencia: document.getElementById('frequencia').value,
    latitude: lat,
    longitude: lng
  };

  try {

    const response = await fetch('api_cadastro_v2.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(dados)
    });


    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (err) {
      console.error("Resposta inválida do servidor:", text);
      throw new Error("Erro técnico no servidor.");
    }

    if (json.success) {
      alert('✅ Cadastro realizado com sucesso!\n\nVocê será redirecionado para o login.');
      window.location.href = 'login.html';
    } else {
      alert('❌ Erro: ' + json.message);
    }

  } catch (error) {
    alert('Erro de conexão ao cadastrar. Tente novamente.');
    console.error(error);
  } finally {

    btn.innerText = originalText;
    btn.disabled = false;
  }
});