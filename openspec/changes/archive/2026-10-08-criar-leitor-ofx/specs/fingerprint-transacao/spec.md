## ADDED Requirements

### Requirement: Fingerprint determinístico
Cada `Transacao` MUST ter um `fingerprint` igual ao SHA-256 em hexadecimal minúsculo (64 caracteres) de `codigoBanco|numeroConta|dia|valor|ordinal`, onde:
- `codigoBanco` é o BANKID convertido para inteiro (sem zeros à esquerda);
- `numeroConta` é o ACCTID sem espaços nas pontas;
- `dia` é a data de DTPOSTED como escrita no arquivo, no formato `AAAA-MM-DD`, sem conversão de fuso;
- `valor` é o valor em centavos;
- `ordinal` começa em 1 e conta, na ordem do arquivo, as transações do mesmo extrato com o mesmo `dia` e o mesmo `valor`.

O fingerprint MUST NOT depender de FITID, tipo, nome, memo, horário ou do fuso configurado. O ordinal MUST ser calculado durante a iteração, com um contador por `dia|valor` que começa vazio a cada iteração.

#### Scenario: Mesma entrada, mesmo fingerprint
- **WHEN** o mesmo arquivo é lido duas vezes, ou `transacoes` é percorrida duas vezes
- **THEN** cada transação tem o mesmo `fingerprint` em todas as leituras

#### Scenario: Valor de referência
- **WHEN** a transação é a primeira de valor `-340806` no dia `2023-09-28` da conta `1000550002` do banco `0341`
- **THEN** `fingerprint` é `hash('sha256', '341|1000550002|2023-09-28|-340806|1')`

#### Scenario: Transações idênticas no mesmo dia
- **WHEN** o extrato tem duas tarifas de `-150` no mesmo dia, com o mesmo memo
- **THEN** elas recebem ordinais 1 e 2 e fingerprints diferentes

#### Scenario: Transação diferente intercalada
- **WHEN** entre duas transações de `-150` do mesmo dia existe uma de `9000`
- **THEN** as duas de `-150` continuam com ordinais 1 e 2

#### Scenario: Independente do fuso configurado
- **WHEN** o mesmo arquivo é lido com fuso `UTC` e com fuso `America/Sao_Paulo`
- **THEN** os fingerprints são iguais

### Requirement: Estabilidade entre exportações sobrepostas
O fingerprint MUST ser igual para a mesma transação em exportações diferentes da mesma conta quando o conjunto de transações daquele dia com aquele valor for o mesmo, mesmo que o banco tenha alterado FITID, nome, memo ou horário entre as exportações.

#### Scenario: Banco enriquece o memo depois
- **WHEN** a exportação A tem `<MEMO>RECEBIMENTO PIX` e a exportação B tem `<MEMO>RECEBIMENTO PIX-PIX_CRED 33164021000100 TOKIO MARINE`, mesma conta, mesmo dia e mesmo valor
- **THEN** a transação tem o mesmo `fingerprint` nas duas

#### Scenario: FITID diferente entre exportações
- **WHEN** a mesma transação aparece com FITIDs diferentes em duas exportações
- **THEN** o `fingerprint` é o mesmo nas duas
