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
   cd fazbem_v2-tcc
   ```
2. Crie o banco de dados e importe a estrutura (DDL):
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS fazbem_v2;"
   mysql -u root -p fazbem_v2 < db_schema.sql
   ```
3. *(Opcional)* Popule o banco com dados de demonstração (clientes, pedidos, rotas e catálogo):
   ```bash
   mysql -u root -p fazbem_v2 < db_seeds.sql
   ```
4. Configure as variáveis de ambiente:
   ```bash
   cp .env.example .env
   # Edite o .env com as credenciais do banco e chaves do Mercado Pago
   ```
5. Hospede os arquivos em um servidor web com suporte a PHP 8+ e MySQL/MariaDB (como Apache ou Nginx).

### 👥 Credenciais de Demonstração (com `db_seeds.sql`)
- **Administrador:** `admin@fazbem.com` | Senha: `admin123`
- **Entregador:** `entregador@fazbem.com` | Senha: `equipe123`
- **Separador:** `separador@fazbem.com` | Senha: `equipe123`
- **Clientes:** `carlos@email.com`, `ana@email.com`, `marcos@email.com` | Senha: `cliente123`
