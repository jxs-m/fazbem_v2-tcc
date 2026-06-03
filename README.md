# Faz Bem V2

Sistema de gestão e e-commerce focado na venda e assinatura de kits de produtos, desenvolvido em PHP com abordagem Orientada a Objetos (POO).

## 🚀 Funcionalidades

- **Painel Administrativo Completo:** Dashboard com métricas financeiras detalhadas (Valor Total Pago, Valor Total Esperado no Mês, etc.) e gerenciamento de pedidos.
- **Gestão de Kits:** Automação e sincronização da descrição do "Kit da Semana" com base na seleção de produtos em lote.
- **Integração de Pagamentos:** Checkout Transparente via Mercado Pago (Payment Brick) para o pagamento das faturas mensais.
- **Área do Cliente (Meu Perfil):** Acompanhamento de faturas, histórico de pedidos e visualização de kits.
- **Segurança:** Sistema de autenticação com proteção contra CSRF, sanitização de inputs e consultas seguras ao banco de dados.

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP (POO), MySQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Pagamentos:** API do Mercado Pago

## ⚙️ Instalação e Configuração

1. Clone o repositório:
   ```bash
   git clone https://github.com/jxs-m/fazbem_v2-tcc.git
   ```
2. Importe a estrutura do banco de dados utilizando o arquivo `db_schema.sql`.
3. Configure as credenciais do banco de dados e as chaves da API do Mercado Pago no ambiente (`api_mp_key.php`, `.env`, ou conforme estrutura de configuração do projeto).
4. Hospede os arquivos em um servidor com suporte a PHP (como Apache ou Nginx).
