# Documento de Contexto Refinado: Projeto FazBem V2

Este documento foi elaborado para mapear e consolidar as implementações, correções e arquitetura atuais do projeto, com o objetivo principal de fornecer contexto rico e imediato para instâncias de Inteligência Artificial (como Gemini) e desenvolvedores parceiros.

## 1. Visão Geral da Arquitetura e Tecnologias
O **FazBem V2** é um sistema abrangente de e-commerce e gestão de logística orgânica (ou cestas programadas). O sistema coordena o fluxo desde a aquisição (vitrine e catálogo), as preferências de perfis, até as entregas através de diferentes interfaces.

* **Frontend:**
  * Uso intencional de HTML5, Vanilla JavaScript e Vanilla CSS (pastas `/css` e `/js`), evitando sobrecarga ou build steps de frameworks baseados em Node.
  * Abordagem SPA/Assíncrona utilizando `fetch` para consumir as APIs, e um único `utils.js` para operações transversais (ex: máscara, renders sanitizados).
  * Telas planejadas para "Mobile-first", especificamente para as ferramentas de visualização do fluxo de logística (Entregadores).
  * Integração de mapas com **Leaflet.js** e OpenStreetMap para renderização de geolocalizações precisas.
* **Backend:**
  * PHP nativo e otimizado com rotas funcionais mapeadas através de arquivos soltos na raiz com o padrão `api_*_v2.php`.
  * Classes e design patterns de Modelos isolados dentro de `app/Models/` (Cliente, Pedido, Produto, Preferencia, Producao).
* **Banco de Dados e Segurança:**
  * MySQL usando abstração PDO. Scripts consolidados de versionamento presentes em `app/schema_dump.sql` e `fazbem_v2.sql`.
  * Camada de segurança proprietária `app/Security.php` com utilidades robustas anti-Session Fixation e Rate Limiting (`app/rate_limit.json`).

---

## 2. Últimas Entregas e Desenvolvimentos (Abril 2026)

### 🚚 Sistema Avançado de Logística e Mapas
Este módulo foi substancialmente robustecido para cobrir todas as saídas de mercadorias:
* **Dashboard do Entregador (`entregador.html`):** Contas com regras e rotas que interagem via `api_admin_entregadores_v2.php` e lógica separada em `js/entregador.js`. Há redirecionamento automático (Role-Based Authentication) para usuários cujo papel é "entregador".
* **Geocodificação e Rotas:**
  * Implementação de captura inteligente de mapas no `cadastro.html` e no checkout: Permite ao cliente encontrar ou fincar sua localização geográfica, o que diminui o retrabalho em procurar os endereços baseados unicamente em texto string.
  * Esses dados são salvos rigorosamente no DB, sendo recuperáveis para clusterizar rotas para cada motoboy/entregador visando velocidade de expedição logística.
* **WhatsApp Inteligente:** Funcionalidade incorporada em fluxos cruciais, permitindo avisos automáticos e dinâmicos para contatos salvos de clientes em formato de Click-to-chat.
* **Controle de Produção:** Inserção do modelo `Producao.php`, unindo o encerramento do pedido à gestão dos lotes preparados fisicamente.

### 🛡️ Auditoria de Segurança e Desacoplamento Front-End
Foram aplicadas intensas varreduras de falhas e refatoramentos de dívida técnica recém aprovadas:
* **Sanitização Universal de Client-Side:** Toda interação que renderiza elementos de banco em tela usa filtros estritos para mitigar *Stored Cross-Site Scripting (XSS)*.
* **Opacidade de Backend (Error Masking):** Remoção sensível de retornos descritivos nas falhas das requests PHP, impedindo invasores de rastrear tabelas, colunas SQL e caminhos internos nos modais de erro ou nos Network Tabs.
* **Remoção de Código Embutido:** Toda sintaxe style e scripts `<script>`/`<style>` dentro de páginas como `admin.html`, `carrinho.html` e `catalogo.html` foram expurgadas para arquivos correspondentes na pasta root.

### 👤 Painel de Gestão de Preferências
Para aprofundar personalizações do usuário:
* A API e a View de perfis foram acopladas ao modelo `Preferencia.php`. O usuário pode editar regras flexíveis do que deseja colocar e evitar, dados agora visíveis globalmente para administradores, otimizando assinaturas de longa duração e cestas.

---

## 3. Como Inicializar Contexto de Alterações
Para que o Gemini tenha poder de ação ideal focada, recomenda-se entregar:
1. Este arquivo (`CONTEXTO_PROJETO.md`).
2. O conteúdo dos Models específicos na área a ser editada (ex: se lidar com compras e carrinho, o Model `Pedido.php`).
3. O endpoint da API responsável na view-alvo.

### Resumo do Flow Transacional Crítico:
1. Vitrine -> 2. Catálogo -> 3. Carrinho -> 4. Cadastro/Checkout c/ Mapa (Geo) -> 5. API_Checkout_V2 (DB) -> 6. Model/Produção -> 7. Entrega (Dashboard Leaflet Entregador)
