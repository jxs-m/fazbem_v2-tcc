# Relatório de Execução e Validação — FazBem V2 (Até a Fase 5)

**Data:** 31/10/2026  
**Referência:** `PLANO_CORRECAO.md` e `RELATORIO_REVISAO_GERAL.md`  
**Escopo:** Consolidação técnica das Fases 0 a 5, com ênfase na implementação da **Fase 5 — Testes Automatizados, Segurança e CI**.

---

## 1. Resumo Executivo

As correções críticas e estruturais previstas nas **Fases 0 a 5** do Plano de Correção do FazBem V2 foram implementadas e verificadas por uma pipeline automatizada de Integração Contínua (CI) com banco de dados MySQL/MariaDB isolado (`fazbem_v2_test`).

Ao todo, a validação automatizada executa **52 verificações de sintaxe (`php -l`)**, **7 regras de análise estática de segurança e integridade** e **219 asserções automatizadas** distribuídas em 5 suítes de testes (Fases 1 a 5), atingindo **100% de aprovação (0 falhas)**.

| Fase | Área Principal | Prioridade | Suíte / Validador | Asserções / Checks | Resultado |
|---|---|---|---|---:|---|
| Fase 0 | Baseline, banco isolado e migrações reversíveis | Base | `001` a `004` (`UP`/`DOWN`) | Incluído nas Fases 1–4 | Aprovado |
| Fase 1 | Integridade financeira e Mercado Pago | P0 | `tests/fase1_integridade_financeira_test.php` | 42 asserções | 0 falhas |
| Fase 2 | Autorização operacional e máquina de estados | P0 | `tests/fase2_autorizacao_estados_test.php` | 42 asserções | 0 falhas |
| Fase 3 | Estoque transacional e concorrência | P0 | `tests/fase3_estoque_concorrencia_test.php` | 31 asserções | 0 falhas |
| Fase 4 | Recuperação de conta e preservação histórica | P1 | `tests/fase4_conta_preservacao_test.php` | 21 asserções | 0 falhas |
| Fase 5 | Testes unitários, integração, segurança e CI | P1 | `tests/fase5_testes_seguranca_integracao_test.php` | 83 asserções | 0 falhas |
| Fase 5 (CI) | Lint PHP (`52` arquivos) e análise estática | P1 | `tests/static_analysis.php` + `scripts/ci.sh` | 7 verificações | 0 falhas |
| **Total** | **Todas as suítes e verificações de CI** | — | **`bash scripts/ci.sh`** | **226 verificações** | **0 falhas** |

---

## 2. Entregas da Fase 5 — Testes Automatizados, Segurança e CI

### 2.1 Testes Unitários (`tests/fase5_testes_seguranca_integracao_test.php`)
- **Cálculo de preço e conversão de estoque:**
  - Produto vendido por unidade inteira (`un`).
  - Produto vendido por quilo (`kg`) comprado em gramas (`750g -> 0.750 kg`) ou por unidade estimada (`peso_estimado_g`).
  - Produto vendido em gramas (`g`) e em unidade composta (`200g`).
  - Validação direta de `Producao::calcularEstoqueDecremento()` e formatação/pluralização em `Producao::formatarExibicao()`.
- **Validação de entradas e documentos:**
  - Validação de CPF (`Validator::validarCPF`) para números válidos, sequências repetidas e dígitos verificadores inválidos.
  - Limites de quantidade (`1` a `50` unidades; `50g` a `20000g`), precisão decimal máxima de 3 casas e compatibilidade entre `tipo_venda` e `tipo_compra`.
- **Regras de assinatura, créditos e descontos (`api_gerenciar_assinatura_v2.php`):**
  - Pausa de assinatura ativa gera crédito proporcional na carteira (`valor_mensal / 4` no plano Semanal; `valor_mensal / 2` no plano Quinzenal).
  - Bloqueio de nova pausa/reativação dentro da janela mínima de 24 horas.
  - Bloqueio de reativação quando o cliente já consumiu o saldo de compensação recebido na pausa.
  - Reativação após 24 horas com saldo disponível estorna o crédito da pausa e retorna o status para `Ativa`.
  - Cancelamento de assinatura com fatura pendente: remove a cobrança de mensalidade, preserva o valor de pedidos extras já realizados e limita `valor_desconto_creditos` ao valor dos extras (`min(desconto, valor_extras)`).

### 2.2 Testes de Integração (`tests/fase5_testes_seguranca_integracao_test.php`)
- **Checkout com total adulterado (`api_checkout_v2.php`):**
  - Simula envio de `total = 0.01` e preço unitário manipulado pelo cliente para itens que somam `R$ 17,00`.
  - Confirma que o servidor ignora o valor do cliente, recalcula `R$ 17,00` a partir do banco e envia o valor real ao Mercado Pago.
- **Checkout com pagamento recusado (`rejected`):**
  - Confirma que a recusa do provedor marca o pedido como `Cancelado`, estorna imediatamente o estoque reservado e limpa `semana_ativa_chave` para permitir nova tentativa.
- **Checkout duplicado em paralelo:**
  - Dispara duas requisições simultâneas (`proc_open`) para o mesmo cliente na mesma semana.
  - Confirma que exatamente uma requisição retorna `HTTP 200` e a outra é bloqueada com `HTTP 400`, criando apenas 1 pedido e baixando o estoque uma única vez.
- **Pesagem parcial, negativa e zero (`api_separacao_v2.php`):**
  - Bloqueia pesagem parcial (faltando itens do pedido) e quantidades negativas (`HTTP 400`).
  - Na pesagem com quantidade zero (falta total sem substituição), devolve integralmente as unidades reservadas ao estoque (`Estorno`), recalcula `valor_total` e avança o pedido para `Aguardando Entrega`.
- **Geração concorrente de faturas (`api_faturamento_v2.php?acao=gerar_faturas`):**
  - Executa chamadas simultâneas de faturamento mensal e confirma a criação de apenas 1 fatura por cliente/mês e um único abatimento de `saldo_compensacao`.

### 2.3 Testes de Segurança e Endurecimento (`tests/fase5_testes_seguranca_integracao_test.php`)
- **CSRF ausente, inválido e reutilizado:**
  - Requisições `POST` sem cabeçalho `X-CSRF-Token` ou com token inválido são rejeitadas com `HTTP 403`.
  - O login (`api_login_v2.php`) regenera o ID de sessão e rotaciona o token CSRF, invalidando o token pré-autenticação.
  - O endpoint `logout.php` agora exige `Security::checkCSRF()` em requisições `POST` e destrói a sessão, impedindo reutilização posterior do token.
- **Acesso cruzado entre papéis (`RBAC`):**
  - Verificação cruzada entre `cliente`, `separador`, `entregador` e `admin` garantindo retorno `HTTP 403` em todos os endpoints fora do perfil autorizado (incluindo padronização do código `403` em `api_minha_assinatura_v2.php`).
- **Prevenção contra IDOR (Insecure Direct Object Reference):**
  - `comprovante.php`: Cliente A bloqueado com `HTTP 403` ao tentar visualizar comprovante de pedido ou fatura pertencente ao Cliente B.
  - `api_faturamento_v2.php`: Cliente A bloqueado ao tentar pagar fatura do Cliente B.
  - `api_gerenciar_assinatura_v2.php`: Cliente A impedido de excluir preferência alimentar pertencente ao Cliente B.
  - `api_separacao_v2.php`: Separador impedido de enviar `item_id` pertencente a outro pedido durante a pesagem (`HTTP 400`).
  - `api_logistica_v2.php`: Entregador A impedido de alterar entrega atribuída à rota do Entregador B (`HTTP 403`).
- **Prevenção contra XSS (Cross-Site Scripting):**
  - Adicionado `Security::sanitizeText()` em `app/Security.php` para remover blocos `<script>`/`<style>` e tags HTML na entrada de dados cadastrais e operacionais (`api_cadastro_v2.php`, `api_perfil_v2.php`, `api_admin_produtos_v2.php`, `api_admin_clientes_v2.php` e `api_gerenciar_assinatura_v2.php`).
  - Validado o escape de saída HTML (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) em `comprovante.php`.
- **Validação de Upload de Imagens (`api_admin_produtos_v2.php`):**
  - Bloqueio de script PHP disfarçado com extensão `.jpg` pela verificação de MIME real via `finfo`.
  - Bloqueio de arquivo com magic bytes PNG falsos/corrompidos na decodificação GD (`imagecreatefromstring`).
  - Bloqueio de arquivos com tamanho superior a 5 MB.
- **Rate Limiting, CORS e Sessão Pós-Recuperação de Senha:**
  - Bloqueio com `HTTP 429` ao ultrapassar a janela de tentativas em `rate_limits`.
  - `cors.php` não emite `Access-Control-Allow-Origin` para origens externas não autorizadas nem permite origens `localhost` quando `AMBIENTE=producao`.
  - `Security::validateActiveSession()` invalida imediatamente sessões anteriores quando `session_version` é incrementada na redefinição de senha.

---

## 3. Pipeline de CI e Análise Estática

Foram criados três componentes para automação contínua:

1. **`tests/static_analysis.php`:**
   - Executa `php -l` em todos os arquivos `.php` do projeto.
   - Verifica ausência de marcadores de conflito Git (`<<<<<<<`, `=======`, `>>>>>>>`).
   - Verifica ausência de credenciais de produção do Mercado Pago (`APP_USR-`) hardcoded no código.
   - Verifica ausência de interpolação direta de `$_GET`, `$_POST`, `$_REQUEST` ou `$_COOKIE` em chamadas SQL PDO.
   - Verifica presença obrigatória de `Security::checkCSRF()` em todos os endpoints mutáveis de sessão.
   - Verifica no `db_schema.sql` que `itens_pedido` utiliza `ON DELETE RESTRICT` (sem `ON DELETE CASCADE`) e que todos os pares de migração `001` a `004` (`_up.sql` e `_down.sql`) existem.
2. **`scripts/ci.sh`:**
   - Script unificado de CI que executa a análise estática, detecta ou inicializa automaticamente uma instância MariaDB temporária isolada caso não haja serviço ativo na máquina, e executa em sequência as suítes das Fases 1, 2, 3, 4 e 5 sobre o banco `fazbem_v2_test`.
3. **`scripts/ci.yml`:**
   - Especificação do workflow de CI (PHP 8.3 e container de serviço MariaDB 11.4) para execução automática em cada `push` e `pull_request`.

---

## 4. Arquivos Criados e Modificados na Fase 5

### Novos Arquivos
- `scripts/ci.yml` — Especificação do workflow de Integração Contínua.
- `scripts/ci.sh` — Executor local e de CI para lint, análise estática, migrações e suítes de testes em banco isolado.
- `tests/static_analysis.php` — Verificador estático de sintaxe, segurança e integridade de schema/migrações.
- `tests/fase5_testes_seguranca_integracao_test.php` — Suíte automatizada de testes unitários, de integração e de segurança da Fase 5.

### Arquivos Ajustados / Endurecidos
- `app/Security.php` — Adição de `Security::sanitizeText()` para sanitização de textos contra tags HTML/scripts.
- `app/Models/Producao.php` — Suporte à variável de ambiente `FORCE_CATALOGO_ABERTO` para execução determinística de testes de integração de checkout em qualquer dia/horário.
- `logout.php` — Exigência de `Security::checkCSRF()` em requisições `POST`.
- `comprovante.php` — Padronização dos códigos de resposta HTTP (`400`, `403`, `404`) em acessos não autenticados, IDs inválidos ou tentativas de IDOR.
- `api_minha_assinatura_v2.php` — Padronização do código `HTTP 403` para acesso não autorizado.
- `api_cadastro_v2.php`, `api_perfil_v2.php`, `api_admin_produtos_v2.php`, `api_admin_clientes_v2.php`, `api_gerenciar_assinatura_v2.php` — Aplicação de `Security::sanitizeText()` nos campos textuais de entrada.
- `cors.php` — Exposição da origem validada para inspeção determinística em testes CLI/CI sem alterar o comportamento HTTP.
- `tests/fase1_integridade_financeira_test.php`, `tests/fase2_autorizacao_estados_test.php`, `tests/fase3_estoque_concorrencia_test.php`, `tests/fase4_conta_preservacao_test.php` — Padronização das variáveis de conexão (`TEST_DB_*` / `DB_*`) e criação automática do schema isolado `fazbem_v2_test`.

---

## 5. Como Executar a Pipeline Completa

Para rodar localmente toda a validação de CI (Lint PHP + Análise Estática + Migrações + Fases 1 a 5):

```bash
bash scripts/ci.sh
```
