## ADDED Requirements

### Requirement: Pipeline de estratégias
O `ProcessadorExtrato` MUST receber uma lista ordenada de `Estrategia` e, para cada uma, na ordem da lista, chamar `aceita(Extrato, OfxBruto): bool`; quando a estratégia aceitar, MUST chamar `processa(Extrato, OfxBruto): Extrato` e passar o `Extrato` devolvido para as estratégias seguintes. Estratégias que não aceitarem MUST NOT alterar o extrato. O `ProcessadorExtrato` MUST devolver o `Extrato` resultante da última estratégia.

O `LeitorOfx` MUST aceitar um `ProcessadorExtrato` opcional no construtor; quando omitido, MUST usar `ProcessadorExtrato::padrao()`, que contém, nesta ordem, `DatasSantander` e `DatasBradesco`.

#### Scenario: Estratégias em sequência
- **WHEN** o processador tem as estratégias A e B, as duas aceitam e A altera `dataGeracao`
- **THEN** B recebe o `Extrato` já alterado por A, e o resultado final é o devolvido por B

#### Scenario: Estratégia que não aceita
- **WHEN** nenhuma estratégia aceita o extrato
- **THEN** o processador devolve o mesmo `Extrato` que recebeu

#### Scenario: Leitor com estratégias padrão
- **WHEN** o `LeitorOfx` é criado sem argumentos e lê um arquivo do Bradesco com `<DTSERVER>00000000000000`
- **THEN** `dataGeracao` do resultado é preenchida pela estratégia `DatasBradesco`

### Requirement: Estratégias do pacote preservam as transações
As estratégias incluídas no pacote MUST alterar apenas `dataInicio`, `dataFim` e `dataGeracao`. `codigoBanco`, `numeroConta` e `transacoes` (incluindo o `fingerprint`) MUST sair iguais aos do `Extrato` recebido. Quando precisarem das transações, MUST percorrer `OfxBruto::transacoes()` uma vez, sem guardá-las, lendo só DTPOSTED. Por isso, num arquivo aceito por uma dessas estratégias, um DTPOSTED inválido lança `OfxInvalido` já na leitura.

#### Scenario: Fingerprint inalterado
- **WHEN** um arquivo do Santander com data malformada é lido com e sem estratégias
- **THEN** as transações e seus fingerprints são iguais nas duas leituras

### Requirement: Datas do Santander sem zeros à esquerda
`DatasSantander` MUST aceitar o extrato quando `codigoBanco` for `33` e `dataGeracao`, `dataInicio` ou `dataFim` for `null` com o valor original correspondente no `OfxBruto` (DTSERVER, DTSTART ou DTEND) começando por 10 a 13 dígitos. Para cada uma dessas datas, MUST:
1. listar as leituras válidas no calendário que dividem os dígitos após o ano em mês, dia, hora, minuto e segundo, com 1 ou 2 dígitos cada;
2. quando as leituras caírem em mais de um dia, manter só os dias iguais ou posteriores ao dia da última transação e, se ainda restar mais de um, escolher o menor;
3. usar a leitura do dia escolhido quando houver só uma; quando houver mais de uma (horário ambíguo), usar o dia escolhido às `00:00:00`;
4. aplicar o deslocamento escrito no valor (`[-3:GMT]`) ou, sem ele, o fuso configurado, e converter para o fuso configurado.

Quando não houver nenhuma leitura válida, a data MUST continuar `null`.

#### Scenario: Leitura única
- **WHEN** `<DTSERVER>2026017932[-3:GMT]`
- **THEN** `dataGeracao` é `2026-01-07 09:03:02-03:00`

#### Scenario: Dia certo, horário ambíguo
- **WHEN** `<DTSERVER>2024051315034[-3:GMT]` (pode ser 01:50:34, 15:00:34 ou 15:03:04 de 13/05/2024)
- **THEN** `dataGeracao` é `2024-05-13 00:00:00-03:00`

#### Scenario: Dia desempatado pela última transação
- **WHEN** `<DTSERVER>2023111093434[-3:GMT]` (pode ser 11/01, 01/11 ou 10/11 de 2023) e a última transação é de 10/11/2023
- **THEN** `dataGeracao` é `2023-11-10 09:34:34-03:00`

#### Scenario: Mais de um dia possível depois da última transação
- **WHEN** `<DTSERVER>2024013145152[-3:GMT]` (pode ser 03/01 ou 31/01 de 2024) e a última transação é de 03/01/2024
- **THEN** `dataGeracao` é `2024-01-03 14:51:52-03:00`

#### Scenario: Período com o mesmo valor malformado
- **WHEN** DTSERVER, DTSTART e DTEND são `2024051315034[-3:GMT]`
- **THEN** `dataGeracao`, `dataInicio` e `dataFim` são `2024-05-13 00:00:00-03:00`

#### Scenario: Outro banco com data nula
- **WHEN** o extrato tem `codigoBanco` `237` e `dataGeracao` `null`
- **THEN** `DatasSantander` não aceita o extrato

### Requirement: Datas do Bradesco
`DatasBradesco` MUST aceitar o extrato quando `codigoBanco` for `237` e `dataGeracao` for `null`. Ao processar, MUST:
1. preencher `dataGeracao` com `dataFim` (o Bradesco grava em DTEND o dia da exportação);
2. quando houver transações, substituir `dataInicio` pela menor `data` e `dataFim` pela maior `data` entre as transações; sem transações, manter `dataInicio` e `dataFim`.

#### Scenario: Extrato com transações
- **WHEN** `<DTSERVER>00000000000000`, `<DTSTART>20260611120000`, `<DTEND>20260611120000` e transações de 01/06/2026 a 26/06/2026, todas às 12:00
- **THEN** `dataGeracao` é `2026-06-11 12:00:00`, `dataInicio` é `2026-06-01 12:00:00` e `dataFim` é `2026-06-26 12:00:00`

#### Scenario: Extrato sem transações
- **WHEN** `<DTSERVER>00000000000000`, `<DTSTART>20260611120000`, `<DTEND>20260611120000` e nenhuma transação
- **THEN** `dataGeracao`, `dataInicio` e `dataFim` são `2026-06-11 12:00:00`

#### Scenario: Bradesco com data de geração preenchida
- **WHEN** o extrato tem `codigoBanco` `237` e `dataGeracao` preenchida
- **THEN** `DatasBradesco` não aceita o extrato
