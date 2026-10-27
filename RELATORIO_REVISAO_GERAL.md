# Relatório de Revisão Geral — FazBem V2

**Data da revisão:** 27/10/2026
**Escopo:** backend PHP, banco MySQL, frontend HTML/CSS/JavaScript, autenticação, autorização, checkout, Mercado Pago, faturamento, estoque, separação e logística.

## 1. Resumo executivo

O FazBem V2 já possui uma base funcional de e-commerce e operação logística para kits de hortaliças. O repositório contém telas para cliente, administração, separação e entrega; APIs PHP; modelos orientados a objetos; esquema de banco relacional; integração com Mercado Pago; controle de sessão, CSRF e rate limiting.

O principal risco atual não é a ausência de funcionalidades, mas a falta de consistência transacional entre os módulos. Pagamento, fatura, pedido, estoque e entrega podem assumir estados diferentes quando ocorre uma repetição de requisição, uma falha externa ou duas operações simultâneas. A varredura complementar de 27/10/2026 confirmou esses riscos e identificou ainda uma recuperação de senha sem prova de posse, exclusão destrutiva de histórico e endpoints potencialmente abandonados. Antes de uma publicação real, o sistema deve priorizar a integridade financeira e a autorização operacional.

### Classificação geral

| Área | Situação | Avaliação |
|---|---|---|
| Estrutura do projeto | Funcional, porém sem padrão de dependências ou testes | Média |
| Autenticação | Sessões, hash de senha e CSRF presentes | Média/boa |
| Autorização | Papéis existem, mas alguns endpoints não limitam o recurso afetado | Média/risco alto em logística |
| Checkout | Recalcula preços no servidor | Média |
| Pagamentos | Integração existe, mas falta idempotência e reconciliação completa | Crítica |
| Faturamento | Fluxo implementado, porém com risco de inconsistência | Crítica |
| Estoque | Modelo e endpoints existem, mas a reserva/baixa não está fechada | Alta |
| Separação e pesagem | Fluxo funcional, precisa de validações de domínio | Média/alta |
| Logística | Mapa e atualização de status funcionam, mas falta escopo por rota | Alta |
| Frontend | Abrangente, com risco de XSS por uso distribuído de `innerHTML` | Média |
| Testes e CI | Não identificados | Crítica |
| Documentação | README inicial e documentação acadêmica presentes | Média |
| Código não utilizado/configuração | Endpoints sem consumidores conhecidos e carregamento alternativo de ambiente | Baixa, com risco operacional |

## 2. O que foi verificado

- Todos os arquivos PHP foram submetidos a `php -l` com PHP 8.3.33.
- Não foram encontrados erros de sintaxe PHP.
- Não foram encontrados `composer.json`, `package.json`, PHPUnit ou uma suíte automatizada de testes.
- O estado do Git estava limpo durante a revisão.
- Foram revisados os principais endpoints, modelos, esquema SQL, configuração de sessão, CORS, integração de pagamento e telas operacionais.
- Em 27/10/2026 foi realizada uma varredura complementar específica para falhas de lógica, segurança e código morto/redundante.
- A varredura complementar identificou 11 achados: 9 de alta ou média prioridade e 2 de baixa prioridade, incluindo os riscos já descritos neste relatório e dois pontos adicionais de manutenção.
- O arquivo `.env` não aparece rastreado no Git; existe `.env.example`.
- Existem regras `.htaccess` para bloquear arquivos de ambiente, código interno e execução em `uploads/`.

A revisão foi estática. Não foi possível validar um fluxo completo com banco MySQL, Mercado Pago real, webhook externo ou navegador em produção.

## 3. Pontos positivos

### Backend e banco

- Uso predominante de PDO e consultas parametrizadas.
- `password_hash()` e `password_verify()` são usados para senhas.
- Há transações em criação de pedidos, pesagem, geração de faturas e alterações de assinatura.
- O schema usa InnoDB e chaves estrangeiras.
- Há separação inicial entre modelos em `app/Models/` e endpoints HTTP.
- O checkout recalcula produtos e preços a partir do banco, em vez de confiar apenas no total enviado pelo navegador.

### Segurança

- Sessões usam `HttpOnly`.
- Existe token CSRF para operações de alteração.
- Há rate limiting persistido no banco.
- O CORS tem lista de origens permitidas.
- Upload de imagens valida MIME, tamanho e conversão para WebP.
- O webhook possui validação de assinatura quando `MP_WEBHOOK_SECRET` está configurado.
- Existem headers de segurança no `.htaccess`.

### Produto e operação

- O domínio principal está representado: assinaturas, kits, pedidos, faturas, substituições, estoque, separação, pesagem, rotas e entrega.
- A aplicação considera produtos vendidos por unidade e por peso.
- O operador consegue acompanhar pedidos, preferências de clientes e endereços georreferenciados.
- O frontend tem telas específicas para cliente, admin, separador e entregador.

## 4. Achados críticos

### 4.1 Faturamento pode marcar pedidos não separados como pagos

O cálculo de extras filtra pedidos cujo status de entrega é diferente de `Em separação`. Entretanto, o `UPDATE` posterior altera todos os pedidos `Extra` e `Avulso` ainda pendentes daquele cliente, sem repetir o filtro de status.

**Risco:** pedidos ainda não pesados ou não entregues podem ser considerados faturados antes de entrarem no total da fatura. Isso pode produzir divergência entre a fatura e os pedidos.

**Arquivo:** [api_faturamento_v2.php](api_faturamento_v2.php)

**Correção recomendada:**

- Selecionar previamente os IDs dos pedidos realmente incluídos na fatura.
- Atualizar apenas esses IDs.
- Persistir uma relação explícita entre pedido e fatura.
- Criar teste para pedido `Em separação`, pedido `Aguardando Entrega` e pedido `Entregue`.

### 4.2 Faturas podem ser duplicadas em concorrência

A geração verifica se existe uma fatura para o usuário e mês, mas a verificação acontece antes da inserção e não há uma restrição única equivalente no banco.

**Risco:** duas requisições administrativas simultâneas podem criar duas faturas para o mesmo cliente e mês, além de debitar o saldo duas vezes.

**Correção recomendada:**

- Adicionar índice único em `faturas_mensais(usuario_id, mes_referencia)`.
- Fazer a verificação e inserção dentro de uma transação apropriada.
- Tratar erro de chave duplicada como operação já concluída.
- Preferir um comando idempotente de geração de faturas.

### 4.3 Idempotência do Mercado Pago não está implementada de forma efetiva

`MercadoPagoService` gera uma nova chave com `uniqid()` para cada chamada. Uma repetição causada por refresh, timeout ou retry recebe uma chave diferente.

**Risco:** o mesmo cliente pode ser cobrado mais de uma vez.

**Correção recomendada:**

- Criar uma chave idempotente antes da chamada externa.
- Persistir a chave na fatura ou pedido.
- Reutilizar a chave em retries do mesmo pagamento.
- Não permitir novo pagamento quando já existe uma transação aprovada ou em processamento.

### 4.4 Webhook não reconcilia pedidos avulsos

O checkout pode criar pagamento associado a um pedido, mas o webhook procura principalmente uma fatura por `external_reference` ou `transacao_id`. O fluxo do pedido não recebe a mesma reconciliação completa do fluxo de fatura.

**Risco:** um pagamento pendente que seja aprovado depois pode não atualizar o pedido local.

**Correção recomendada:**

- Enviar `external_reference` contendo tipo e ID do recurso.
- Criar uma tabela ou serviço de transações de pagamento.
- Atualizar pedido ou fatura conforme o recurso original.
- Tornar o webhook idempotente e seguro para reprocessamento.
- Validar valor, usuário e moeda antes de confirmar o status local.

### 4.5 Entregador pode alterar pedido fora da própria rota

O endpoint de logística valida que o usuário é `admin` ou `entregador`, mas recebe um `pedido_id` e altera o pedido sem confirmar rota, atribuição, data ou relacionamento com aquele entregador.

**Risco:** um entregador autenticado pode atualizar pedidos de outra rota ou alterar pedidos que não deveria acessar.

**Correção recomendada:**

- Buscar a rota atribuída ao usuário.
- Atualizar somente pedidos vinculados a essa rota.
- Bloquear transições inválidas de estado.
- Registrar usuário, data, estado anterior e estado novo em auditoria.

### 4.6 Exclusão de produto pode remover histórico

A relação entre itens de pedido e produtos utiliza `ON DELETE CASCADE`.

**Risco:** excluir um produto pode apagar itens de pedidos antigos, prejudicando recibos, relatórios e auditoria financeira.

**Correção recomendada:**

- Trocar para `ON DELETE RESTRICT` ou `NO ACTION`.
- Implementar exclusão lógica com `ativo` ou `excluido_em`.
- Manter snapshots de nome, unidade e preço no item do pedido.

### 4.7 Recuperação de senha não comprova posse da conta

O endpoint redefine a senha depois de comparar e-mail, telefone e CPF, sem token aleatório, código temporário, expiração, uso único ou invalidação das sessões existentes.

**Arquivo:** [api_recuperar_senha.php](api_recuperar_senha.php)

**Risco:** quem obtiver ou descobrir dados pessoais do cliente pode assumir a conta sem controlar o e-mail ou outro canal verificado. A combinação de dados cadastrais não deve ser tratada como fator suficiente de autenticação.

**Correção recomendada:**

- Criar uma solicitação de recuperação com token aleatório armazenado de forma segura.
- Enviar o token por canal previamente verificado, com validade curta e uso único.
- Responder de forma uniforme para evitar enumeração de contas.
- Invalidar sessões e tokens anteriores depois da troca da senha.
- Registrar a operação sem armazenar o token em logs.

### 4.8 Checkout não valida nem reserva estoque de forma transacional

O checkout consulta dados de produto e calcula preço, mas não lê `estoque_atual`, não reserva quantidade e não realiza baixa condicional protegida contra concorrência. O controle de um pedido semanal também depende de uma consulta anterior à inserção.

**Arquivos:** [app/Models/Pedido.php](app/Models/Pedido.php), [api_checkout_v2.php](api_checkout_v2.php)

**Risco:** dois checkouts concorrentes podem consumir a mesma disponibilidade, permitir estoque negativo ou criar pedidos acima do limite semanal. Isso compromete a operação e a conciliação financeira.

**Correção recomendada:**

- Validar estoque e quantidade no servidor.
- Reservar ou baixar com `UPDATE` condicional (`estoque_atual >= quantidade`) dentro de transação.
- Registrar a movimentação de estoque e definir estorno para cancelamento ou falta.
- Adicionar uma regra única/idempotente para o limite semanal.
- Testar concorrência e rollback após falha do pagamento.

### 4.9 Endpoints sem consumidores conhecidos devem ser confirmados

Não foram encontradas referências internas para [api_estoque_v2.php](api_estoque_v2.php) e [api_admin_relatorio_compras.php](api_admin_relatorio_compras.php), embora os demais endpoints administrativos possuam chamadas identificáveis no frontend.

**Risco:** código abandonado pode permanecer publicado, aumentar a superfície de ataque e manter regras divergentes das interfaces atuais. A ausência de referência interna não prova que não existam consumidores externos.

**Correção recomendada:**

- Confirmar integrações externas, tarefas agendadas e chamadas manuais antes de remover.
- Se não houver consumidor, remover ou desativar as rotas.
- Se forem necessárias, documentar os consumidores e aplicar autorização, CSRF e validações no mesmo padrão dos endpoints atuais.

### 4.10 Carregamento implícito de `.env.php` é redundante e ambíguo

`Env::load()` procura automaticamente um arquivo com o sufixo `.php` antes de carregar o caminho informado. As chamadas atuais apontam para `.env`, portanto um `.env.php` presente no ambiente pode substituir silenciosamente a configuração esperada.

**Arquivo:** [app/Env.php](app/Env.php)

**Risco:** credenciais, banco ou ambiente incorretos podem ser carregados sem indicação clara ao operador, especialmente em instalações que mantêm arquivos de configuração antigos.

**Correção recomendada:**

- Carregar exclusivamente o caminho explicitamente configurado.
- Se `.env.php` for necessário, torná-lo uma opção explícita e documentada.
- Validar variáveis obrigatórias e registrar apenas o nome do ambiente ativo, nunca os segredos.

### 4.11 Resumo da varredura complementar

Os achados confirmados nesta atualização são:

| Categoria | Quantidade | Prioridade |
|---|---:|---|
| Integridade financeira e pagamentos | 4 | Alta |
| Autorização e autenticação | 2 | Alta |
| Integridade operacional e histórica | 3 | Média |
| Código morto ou configuração redundante | 2 | Baixa |

Os itens de idempotência, webhook, faturamento concorrente, autorização de logística, transições de entrega e recuperação de senha devem ser tratados antes de produção. Os itens de estoque e preservação de histórico devem ser tratados antes de uso operacional contínuo.

## 5. Achados de alta prioridade

### 5.1 Estoque não está fechado de ponta a ponta

O schema possui `estoque_atual` e `movimentacoes_estoque`, e há lógica para calcular decremento, mas o fluxo completo precisa garantir reserva, baixa, estorno e concorrência.

**Pendências:**

- Impedir dois checkouts de consumirem o mesmo estoque.
- Definir quando a reserva ocorre.
- Definir quando a baixa definitiva ocorre.
- Registrar toda alteração em `movimentacoes_estoque`.
- Repor ou estornar estoque em cancelamento e falta na separação.
- Bloquear valores negativos.

### 5.2 Checkout não possui chave de operação do cliente

O checkout verifica se já existe pedido na semana, mas duas requisições paralelas podem passar pela verificação antes de qualquer uma inserir o pedido.

**Correção:** índice ou regra transacional para impedir duplicidade, além de uma chave de idempotência enviada pelo frontend e validada no backend.

### 5.3 Falta validação completa de domínio no carrinho

O backend valida existência do produto e recalcula o preço, mas deve também validar:

- quantidade máxima por produto;
- quantidade mínima e precisão decimal;
- unidade escolhida;
- produto ativo e dentro da validade;
- estoque disponível;
- produtos duplicados no mesmo carrinho;
- limite semanal;
- consistência entre `tipo_venda`, unidade e quantidade.

### 5.4 Transições de pedido são livres demais

Alguns endpoints aceitam status de uma lista, mas não verificam o estado anterior. Assim, pode ser possível marcar diretamente um pedido como `Entregue`, voltar para `Em separação` ou alterar um pedido já finalizado.

**Correção:** centralizar a máquina de estados em um serviço ou método de domínio.

### 5.5 Rate limiting sujeito a corrida

O rate limit faz `SELECT` e depois `INSERT` ou `UPDATE`. Em concorrência, duas requisições podem observar o mesmo estado e atualizar contadores incorretamente; o primeiro insert também pode colidir com o índice único.

**Correção:** usar `INSERT ... ON DUPLICATE KEY UPDATE`, transação curta, incremento atômico e limpeza periódica de registros antigos.

### 5.6 Recuperação de senha precisa de fluxo mais forte

O reset depende de e-mail, telefone e CPF, e altera a senha diretamente. Não há token temporário, expiração, auditoria ou invalidação das sessões existentes.

**Correção:** criar solicitação de recuperação com token aleatório, expiração curta, uso único, resposta uniforme e invalidação de sessões após redefinição.

## 6. Segurança do frontend e infraestrutura

### 6.1 Uso distribuído de `innerHTML`

O frontend utiliza `innerHTML` em diversos arquivos. Alguns valores passam por `escapeHTML`, mas a proteção é manual e inconsistente.

**Recomendação:**

- Preferir `textContent` para texto.
- Usar criação de elementos para ações e atributos.
- Centralizar sanitização.
- Nunca inserir em HTML valores de API sem escape explícito.
- Auditar URLs geradas, atributos `href`, `src`, `onclick` e estilos.

### 6.2 CSP permissiva

O `.htaccess` permite `unsafe-inline` e `unsafe-eval`, além de vários domínios externos.

**Recomendação:**

- Remover scripts inline progressivamente.
- Usar nonce ou hash para scripts necessários.
- Remover `unsafe-eval`.
- Reduzir domínios permitidos a dependências realmente usadas.
- Monitorar violações com `Content-Security-Policy-Report-Only` antes da aplicação definitiva.

### 6.3 Credenciais de demonstração previsíveis

O README e o SQL de carga inicial informam credenciais como `admin123`, `equipe123` e `cliente123`.

**Recomendação:**

- Remover credenciais reais de documentação pública.
- Gerar senha administrativa durante a instalação.
- Exigir troca da senha no primeiro login.
- Manter seeds de demonstração separados da instalação de produção.

### 6.4 Logs sensíveis

Há registro do corpo completo de requisições no pagamento de faturas.

**Risco:** logs podem conter dados de identificação ou informações retornadas pelo provedor de pagamento.

**Recomendação:** registrar apenas IDs, status, duração, código HTTP e identificadores não sensíveis.

### 6.5 CORS e cookies

A configuração é restritiva em comparação com um CORS aberto, mas deve ser validada no ambiente real, especialmente quando frontend e backend estiverem em domínios separados.

**Verificar:**

- HTTPS obrigatório em produção.
- `SameSite` compatível com a topologia final.
- Origem exata, sem hosts de desenvolvimento em produção.
- Proxy reverso preservando HTTPS corretamente.
- Renovação da sessão após autenticação.

## 7. Banco de dados e integridade

### Índices e restrições recomendados

Adicionar ou revisar:

- `UNIQUE(usuario_id, mes_referencia)` em `faturas_mensais`.
- Índice para pedidos por `usuario_id`, `data_pedido` e status.
- Índice para pedidos por `rota_id`, `ordem_entrega`.
- Índice para itens ainda não faturados.
- Restrição para impedir múltiplos endereços principais por usuário, quando suportado pelo banco.
- Restrição para evitar substituição de um produto por ele mesmo.
- Validação de valores monetários não negativos.

### Modelo financeiro

O valor final do pedido e da fatura deve ser auditável. Recomenda-se armazenar:

- valor original;
- descontos e créditos aplicados;
- valor final;
- origem do crédito;
- ID externo do pagamento;
- chave idempotente;
- data de cada transição;
- usuário ou processo responsável.

### Exclusões

Pedidos, itens, faturas e transações financeiras devem ser preservados. A preferência deve ser arquivamento ou exclusão lógica, não cascata destrutiva de histórico.

## 8. Fluxos que precisam ser consolidados

### 8.1 Geração de kits

Existem endpoints com responsabilidades semelhantes para editar o kit e gerar pedidos. Eles possuem comportamentos diferentes: um remove e recria pedidos, enquanto outro ignora pedidos existentes.

**Ação:** escolher um único serviço de geração, definir se a operação é reversível e bloquear alterações depois que a separação começar.

### 8.2 Assinaturas

O fluxo de pausar, reativar, cancelar e alterar plano precisa definir claramente:

- qual período é creditado;
- quando o crédito pode ser usado;
- como uma fatura pendente afeta a assinatura;
- o que acontece com pedido já gerado;
- como funciona troca de frequência no meio do ciclo;
- se existe limite para alterações repetidas.

### 8.3 Pesagem

O endpoint de separação calcula o novo total, mas deve validar:

- se todos os itens foram pesados;
- se a quantidade real é não negativa;
- se o operador pode alterar aquele pedido;
- se o pedido já foi pesado;
- se a alteração financeira entra na fatura correta;
- se faltas geram estoque, crédito ou substituição.

### 8.4 Rotas

A rota deve possuir ciclo próprio:

1. criada;
2. pedidos vinculados;
3. entregador atribuído;
4. iniciada;
5. pedidos entregues ou com ocorrência;
6. concluída.

Também é recomendável armazenar tentativas de entrega, motivo de falha, localização opcional e observação do entregador.

## 9. Frontend e experiência de uso

### Melhorias técnicas

- Centralizar tratamento de erro de `fetch`.
- Verificar `response.ok` antes de interpretar JSON.
- Mostrar estados de carregamento, vazio e erro de forma consistente.
- Evitar múltiplas chamadas redundantes ao carregar perfil, pedidos e faturas.
- Versionar CSS e JavaScript para evitar cache antigo.
- Remover `onclick` inline e usar listeners.
- Validar formulários também no backend, sem depender do navegador.
- Adicionar acessibilidade: foco visível, labels, teclado, mensagens para leitores de tela e contraste.
- Testar layout em celular, especialmente separador e entregador.

### Melhorias de produto

- Exibir claramente previsão de preço e preço final após pesagem.
- Mostrar estado do pagamento e do pedido como conceitos separados.
- Informar ao cliente quando uma fatura está pendente e quais pedidos ela contém.
- Permitir histórico detalhado de faturas e recibos.
- Mostrar ocorrências de entrega.
- Adicionar filtros e paginação ao painel administrativo.

## 10. Arquitetura e manutenção

A aplicação possui modelos, mas ainda mistura regras de negócio diretamente nos arquivos `api_*.php`. Isso aumenta duplicação e dificulta testes.

### Evolução recomendada

- Criar camadas `Controller`, `Service`, `Repository` e `Validator` gradualmente.
- Centralizar autenticação e autorização.
- Criar respostas JSON padronizadas.
- Centralizar tratamento de exceções.
- Criar enumerações ou constantes para status.
- Substituir `require_once` espalhado por autoload via Composer.
- Definir timezone em um único ponto de inicialização.
- Criar configuração única para ambiente, URLs, limites e integrações.
- Evitar `die()` dentro da camada de banco; lançar exceção tratável.

## 11. Testes recomendados

### Testes unitários

- Cálculo de estoque por unidade, grama e quilograma.
- Cálculo de preço de item fracionado.
- Validação de CPF, e-mail, telefone e senha.
- Regras de pausa e reativação.
- Máquina de estados de pedido.
- Cálculo de créditos e descontos.

### Testes de integração

- Cadastro com assinatura e endereço.
- Login, regeneração de sessão e logout.
- Checkout com total adulterado no cliente.
- Checkout duplicado em paralelo.
- Geração de fatura repetida.
- Pagamento aprovado, pendente, recusado e estornado.
- Reprocessamento do webhook.
- Pesagem parcial, peso zero e peso negativo.
- Atualização de entrega fora da rota.
- Exclusão de produto usado em pedido.

### Testes de segurança

- CSRF ausente, inválido e reutilizado.
- Acesso cruzado entre cliente, admin, separador e entregador.
- IDOR em pedidos, faturas, produtos e rotas.
- XSS em nome, endereço, observação, preferência e produto.
- Upload de arquivo inválido e arquivo com extensão enganosa.
- Rate limit sob concorrência.
- CORS com origem não autorizada.
- Sessão após recuperação de senha.

### Testes de aceitação

- Um ciclo completo semanal desde a escolha do kit até a entrega.
- Um produto fracionado pesado com diferença entre estimativa e valor real.
- Uma assinatura pausada e reativada.
- Uma fatura paga por cartão e outra por Pix.
- Um pagamento aprovado pelo webhook depois de ficar pendente.
- Uma falha de rede durante o checkout sem cobrança duplicada.

## 12. Plano de execução sugerido

### Fase 1 — Bloqueio de riscos financeiros

- Corrigir seleção de pedidos incluídos no faturamento.
- Adicionar unicidade de fatura por cliente e mês.
- Implementar idempotência de pagamento.
- Corrigir `external_reference` e reconciliação do webhook.
- Adicionar logs sem dados sensíveis.

**Critério de conclusão:** nenhuma operação repetida cria cobrança, fatura ou crédito duplicado.

### Fase 2 — Autorização e estados

- Restringir entregador à própria rota.
- Centralizar transições de pedido.
- Restringir separador aos pedidos operacionais permitidos.
- Registrar auditoria de alterações.

**Critério de conclusão:** cada papel só altera recursos autorizados e estados válidos.

### Fase 3 — Estoque e operação

- Implementar reserva e baixa de estoque.
- Registrar movimentações.
- Tratar faltas, substituições e estornos.
- Evitar estoque negativo sob concorrência.

**Critério de conclusão:** estoque, pedido e movimentações permanecem conciliáveis após sucesso, erro ou cancelamento.

### Fase 4 — Testes e qualidade

- Adicionar Composer e autoload.
- Criar testes unitários e de integração.
- Adicionar análise estática e lint ao CI.
- Criar ambiente de testes com banco isolado.

**Critério de conclusão:** os fluxos críticos executam automaticamente em cada alteração.

### Fase 5 — Segurança e acabamento

- Remover credenciais padrão da documentação pública.
- Fortalecer CSP.
- Auditar `innerHTML`.
- Melhorar recuperação de senha.
- Adicionar acessibilidade, paginação e tratamento uniforme de erros.

## 13. Checklist de publicação

- [ ] `.env` configurado fora do Git.
- [ ] Senhas administrativas alteradas.
- [ ] HTTPS ativo e redirecionamento HTTP configurado.
- [ ] Webhook Mercado Pago com segredo validado.
- [ ] Teste de pagamento em ambiente sandbox concluído.
- [ ] Backup e restauração do banco testados.
- [ ] Índices e constraints aplicados.
- [ ] Logs sem dados de cartão, CPF completo ou tokens.
- [ ] Uploads sem execução de scripts.
- [ ] Rate limiting funcionando em produção.
- [ ] Monitoramento de erros configurado.
- [ ] Teste de autorização por papel concluído.
- [ ] Teste de duplicação de checkout concluído.
- [ ] Teste de reprocessamento do webhook concluído.
- [ ] Teste de ciclo completo do pedido concluído.

## 14. Conclusão

O FazBem V2 tem escopo relevante e uma implementação ampla para um sistema acadêmico e operacional. A prioridade não deve ser adicionar muitas telas novas antes de consolidar as regras que conectam pagamento, fatura, pedido, estoque e entrega.

Com a correção dos riscos financeiros, o fechamento das autorizações por recurso, a implementação de idempotência e a criação de testes de integração, o sistema terá uma base muito mais confiável para demonstração, avaliação acadêmica e eventual uso real.

O próximo marco recomendado é concluir a Fase 1 e criar testes automatizados para os cenários de faturamento e pagamento antes de continuar expandindo funcionalidades.
