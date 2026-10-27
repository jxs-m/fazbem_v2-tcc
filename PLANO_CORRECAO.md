# Plano de Correção — FazBem V2

**Data:** 27/10/2026  
**Base:** Relatório de Revisão Geral do FazBem V2  
**Objetivo:** reduzir os riscos de integridade financeira, autorização, estoque e segurança antes da publicação em produção.

## 1. Objetivo e escopo

Este plano organiza as correções identificadas na revisão do sistema. A execução deve priorizar os fluxos que podem gerar cobrança duplicada, perda de histórico, acesso indevido ou divergência entre pedido, pagamento, fatura e estoque.

O plano não substitui a validação funcional em ambiente de testes. Cada fase deve ser implementada com migração, testes e evidências de validação correspondentes.

## 2. Critérios gerais de execução

- Não publicar alterações financeiras sem migração compatível e backup validado.
- Toda operação externa de pagamento deve ser idempotente e reconciliável.
- Toda alteração operacional deve validar papel, recurso, estado anterior e estado novo.
- Toda baixa ou reserva de estoque deve ocorrer dentro de transação e gerar movimentação.
- Dados financeiros, pedidos, itens e transações não devem ser apagados por cascata destrutiva.
- Erros devem ser registrados de forma útil, sem tokens, dados de cartão, CPF completo ou credenciais.
- O comportamento deve ser coberto por testes automatizados antes da liberação.

## 3. Priorização

| Prioridade | Área | Risco principal | Fase |
|---|---|---|---|
| P0 | Pagamentos e faturamento | Cobrança ou débito duplicado, fatura inconsistente | 1 |
| P0 | Autorização operacional | Entregador ou operador alterando recurso indevido | 2 |
| P0 | Estoque | Venda acima da disponibilidade ou estoque negativo | 3 |
| P1 | Recuperação de conta | Tomada de conta por dados cadastrais | 4 |
| P1 | Histórico | Perda de registros financeiros e de pedidos | 4 |
| P1 | Testes e CI | Regressões não detectadas | 5 |
| P2 | Frontend e infraestrutura | XSS, CSP permissiva e falhas operacionais | 6 |

## 4. Fase 0 — Baseline e preparação

### Atividades

- Confirmar o estado atual do Git e das configurações de ambiente.
- Executar lint de todos os arquivos PHP.
- Confirmar o schema utilizado por cada ambiente.
- Identificar consumidores internos e externos dos endpoints.
- Definir ambiente de testes com banco isolado.
- Preparar migrações reversíveis para índices, colunas e restrições.

### Entregáveis

- Baseline técnico registrado.
- Banco de testes disponível.
- Estratégia de migração e rollback definida.

### Critério de conclusão

As alterações podem ser executadas e revertidas sem modificar dados de produção de forma irreversível.

## 5. Fase 1 — Integridade financeira e Mercado Pago

**Prioridade:** P0  
**Dependência:** Fase 0

### 5.1 Faturamento

- Selecionar previamente os pedidos elegíveis para a fatura.
- Atualizar somente os pedidos selecionados.
- Manter a criação da fatura, débito de crédito e marcação dos pedidos na mesma transação.
- Adicionar unicidade por `usuario_id` e `mes_referencia`.
- Tratar concorrência como operação já concluída quando outra requisição criar a fatura primeiro.
- Relacionar explicitamente pedidos faturados à fatura, quando o modelo permitir.

### 5.2 Idempotência de pagamento

- Criar uma chave idempotente estável por operação de negócio.
- Persistir a chave antes da chamada ao Mercado Pago.
- Reutilizar a chave em retries e timeouts.
- Bloquear nova cobrança para pagamento aprovado ou em processamento.
- Aplicar o mesmo padrão a faturas, pedidos avulsos e pedidos extras.

### 5.3 Reconciliação e webhook

- Usar `external_reference` tipada, distinguindo fatura e pedido.
- Buscar os detalhes do pagamento no Mercado Pago antes de atualizar o banco.
- Validar identificador, valor, moeda, usuário e estado local.
- Atualizar o recurso original: fatura ou pedido.
- Tornar o webhook seguro para reprocessamento.
- Responder corretamente a notificações duplicadas, pendentes, aprovadas, recusadas e estornadas.

### 5.4 Configuração e erros

- Tornar explícito o carregamento de `.env`.
- Validar credenciais obrigatórias conforme o ambiente.
- Diferenciar falha de transporte, erro HTTP e rejeição de pagamento.
- Padronizar respostas da integração.
- Remover dados sensíveis dos logs.

### Critério de conclusão

Repetições de checkout, retry externo ou reprocessamento de webhook não criam cobrança, fatura, débito ou crédito duplicado. Um pagamento aprovado atualiza o recurso local correto.

## 6. Fase 2 — Autorização e máquina de estados

**Prioridade:** P0  
**Dependência:** Fase 1

### Atividades

- Restringir entregadores aos pedidos da própria rota, data e atribuição.
- Restringir separadores aos pedidos autorizados para a operação.
- Centralizar transições de status em serviço ou método de domínio.
- Definir transições permitidas, por exemplo:
  - `Em separação` → `Aguardando Entrega`;
  - `Aguardando Entrega` → `Saiu para entrega`;
  - `Saiu para entrega` → `Entregue`.
- Bloquear retorno indevido de estados finalizados.
- Registrar usuário, data, estado anterior, estado novo e motivo.

### Critério de conclusão

Cada papel só altera os recursos permitidos e nenhuma API aceita transição inválida ou alteração fora do escopo operacional.

## 7. Fase 3 — Estoque e concorrência

**Prioridade:** P0  
**Dependência:** Fase 2

### Atividades

- Validar produto ativo, unidade, quantidade, precisão e limites.
- Rejeitar produtos duplicados ou incompatíveis no carrinho.
- Reservar ou baixar estoque com `UPDATE` condicional dentro de transação.
- Impedir valores negativos.
- Registrar cada entrada, saída, descarte, reserva e estorno.
- Definir quando ocorre reserva e quando ocorre baixa definitiva.
- Estornar ou repor estoque em cancelamento, falta ou substituição.
- Proteger o limite semanal contra duas requisições concorrentes.

### Critério de conclusão

Após sucesso, erro, retry ou cancelamento, pedido, estoque e movimentações permanecem conciliáveis e o estoque nunca fica negativo.

## 8. Fase 4 — Conta e preservação histórica

**Prioridade:** P1  
**Dependência:** Fase 0

### 8.1 Recuperação de senha

- Criar solicitação de recuperação com token aleatório.
- Armazenar somente o hash do token.
- Definir expiração curta e uso único.
- Enviar o token por canal previamente verificado.
- Responder uniformemente para evitar enumeração de contas.
- Invalidar sessões e tokens anteriores após a troca.
- Auditar a operação sem registrar o token.

### 8.2 Preservação de dados

- Trocar cascatas destrutivas de itens de pedidos por `RESTRICT` ou `NO ACTION`.
- Implementar exclusão lógica de produtos.
- Preservar snapshots de nome, unidade e preço nos itens vendidos.
- Preservar pedidos, faturas e transações financeiras.

### Critério de conclusão

Uma pessoa sem posse do canal verificado não consegue redefinir a senha, e excluir ou desativar um produto não remove o histórico de vendas.

## 9. Fase 5 — Testes automatizados e CI

**Prioridade:** P1  
**Dependências:** Fases 1, 2 e 3

### Testes unitários

- Cálculo de preço por unidade, grama e quilograma.
- Validação de quantidades e unidades.
- Créditos e descontos.
- Máquina de estados.
- Regras de assinatura.

### Testes de integração

- Checkout com total adulterado.
- Checkout duplicado em paralelo.
- Geração concorrente de fatura.
- Pagamento aprovado, pendente, recusado e estornado.
- Reprocessamento de webhook.
- Atualização de entrega fora da rota.
- Pesagem parcial, zero e negativa.
- Exclusão de produto usado em pedido.

### Testes de segurança

- CSRF ausente, inválido e reutilizado.
- Acesso cruzado entre papéis.
- IDOR em pedidos, faturas, produtos e rotas.
- XSS em campos de usuário e produto.
- Upload inválido.
- Rate limit sob concorrência.
- CORS não autorizado.
- Sessões após recuperação de senha.

### CI

- Lint PHP.
- Testes automatizados.
- Análise estática.
- Validação de migrações.
- Execução em banco isolado.

### Critério de conclusão

Os fluxos críticos falham automaticamente quando uma regressão reintroduz duplicidade, acesso indevido, estoque negativo ou transição inválida.

## 10. Fase 6 — Frontend, infraestrutura e acabamento

**Prioridade:** P2  
**Dependência:** Fase 0

### Atividades

- Substituir `innerHTML` por `textContent` e criação segura de elementos quando possível.
- Centralizar escape e sanitização.
- Auditar URLs, atributos, estilos e eventos inline.
- Remover gradualmente `unsafe-inline` e `unsafe-eval` da CSP.
- Verificar `response.ok` antes de interpretar respostas `fetch`.
- Padronizar estados de carregamento, vazio e erro.
- Adicionar foco visível, labels, teclado e mensagens acessíveis.
- Versionar CSS e JavaScript.
- Remover credenciais padrão da documentação pública.
- Tornar o timezone e configuração de ambiente centralizados.

### Critério de conclusão

As telas principais não inserem dados de API em HTML sem proteção, exibem falhas de rede corretamente e passam por validação básica de acessibilidade.

## 11. Gate de publicação

A publicação só deve ocorrer após todos os itens abaixo:

- [ ] `.env` fora do Git e configurado no ambiente correto.
- [ ] Senhas administrativas alteradas.
- [ ] HTTPS e redirecionamento configurados.
- [ ] Segredo do webhook configurado e validado.
- [ ] Teste sandbox do Mercado Pago concluído.
- [ ] Migrações aplicadas e verificadas.
- [ ] Backup e restauração testados.
- [ ] Índices e restrições confirmados.
- [ ] Logs sem cartão, CPF completo, tokens ou credenciais.
- [ ] Uploads sem execução de scripts.
- [ ] Rate limiting validado.
- [ ] Monitoramento de erros ativo.
- [ ] Testes de autorização por papel concluídos.
- [ ] Testes de duplicação de checkout concluídos.
- [ ] Teste de reprocessamento de webhook concluído.
- [ ] Teste de rollback transacional concluído.

## 12. Ordem recomendada de execução

1. Baseline, banco de testes e migrações.
2. Faturamento e unicidade.
3. Idempotência e reconciliação do Mercado Pago.
4. Autorização e máquina de estados.
5. Estoque transacional.
6. Recuperação de senha e preservação histórica.
7. Testes automatizados e CI.
8. Frontend, CSP, acessibilidade e acabamento.
9. Gate de publicação.

O arquivo anexado de referência do serviço Mercado Pago é somente leitura e não faz parte deste documento nem deve ser editado.
