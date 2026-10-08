# leitura-ofx Specification

## Purpose
TBD - created by archiving change criar-leitor-ofx. Update Purpose after archive.
## Requirements
### Requirement: Leitura de extrato de conta corrente
O `LeitorOfx` MUST ler um arquivo OFX de conta corrente a partir do conteúdo (`ler(string $conteudo)`), de um caminho (`lerArquivo(string $caminho)`) ou de um stream (`lerStream($stream)`), montar um `Extrato` com `codigoBanco` (BANKID), `numeroConta` (ACCTID), `dataInicio` (DTSTART), `dataFim` (DTEND), `dataGeracao` (DTSERVER) e `transacoes`, e entregá-lo ao `ProcessadorExtrato` junto com o `OfxBruto` do arquivo. O resultado MUST ser o `Extrato` devolvido pelo processador. `Extrato` e `Transacao` MUST ser classes `final readonly`. `dataInicio`, `dataFim` e `dataGeracao` MUST ser `?DateTimeImmutable`.

Para montar o `Extrato`, o leitor MUST ler a origem só até a primeira `<STMTTRN>` (ou até o fim de `<BANKTRANLIST>`, se não houver transações).

#### Scenario: Arquivo de conta corrente válido
- **WHEN** o conteúdo de um OFX com um `<STMTRS>` e três `<STMTTRN>` é lido
- **THEN** o resultado é um `Extrato` com os dados da conta, e percorrer `transacoes` produz três `Transacao` na mesma ordem do arquivo

#### Scenario: Arquivo sem transações
- **WHEN** o OFX tem `<BANKTRANLIST>` sem nenhum `<STMTTRN>`
- **THEN** percorrer `transacoes` não produz nenhuma `Transacao`

#### Scenario: Leitura de um caminho
- **WHEN** `lerArquivo('/tmp/extrato.ofx')` é chamado com um arquivo válido
- **THEN** o resultado é o mesmo de `ler(file_get_contents('/tmp/extrato.ofx'))`

### Requirement: Transações sob demanda
`transacoes` MUST ser um objeto `Transacoes` que implementa `IteratorAggregate` e produz cada `Transacao` por um generator, lendo a origem a partir da primeira `<STMTTRN>` no momento da iteração. Nenhuma lista de transações MUST ser mantida em memória pelo pacote. `transacoes` MUST poder ser percorrida mais de uma vez, inclusive em iterações aninhadas, produzindo sempre as mesmas transações.

Erros de transação MUST ser lançados como `OfxInvalido` durante a iteração, ao chegar na transação com problema; as transações anteriores já terão sido produzidas. A iteração MUST ir até o fim da origem e lançar `OfxInvalido` se encontrar outro `<STMTRS>` ou um `<CCSTMTRS>`.

#### Scenario: Memória constante
- **WHEN** um arquivo com 1.000.000 de transações é lido de um stream e todas são percorridas
- **THEN** o pico de memória do processo não cresce com a quantidade de transações (além do controle de ordinais do fingerprint)

#### Scenario: Duas iterações
- **WHEN** `transacoes` é percorrida duas vezes
- **THEN** as duas iterações produzem as mesmas transações, com os mesmos fingerprints

#### Scenario: Transação inválida no meio do arquivo
- **WHEN** a terceira de cinco transações tem `<TRNAMT>1.234,56`
- **THEN** `ler()` devolve o `Extrato` sem erro, e percorrer `transacoes` produz duas `Transacao` e então lança `OfxInvalido`

### Requirement: Origem da leitura
`ler()` MUST manter o conteúdo num stream em memória; `lerArquivo()` MUST abrir o arquivo em modo binário e fechá-lo quando o `Extrato` deixar de ser usado; `lerStream()` MUST usar o stream recebido se ele permitir `fseek` e, caso contrário, copiá-lo para `php://temp`. O pacote MUST NOT fechar um stream recebido do consumidor, e o consumidor MUST mantê-lo aberto enquanto percorrer `transacoes`.

#### Scenario: Stream sem fseek
- **WHEN** `lerStream()` recebe um stream que não permite `fseek`
- **THEN** o conteúdo é copiado para `php://temp` e `transacoes` pode ser percorrida mais de uma vez

#### Scenario: Caminho inexistente
- **WHEN** `lerArquivo()` recebe um caminho que não existe ou não pode ser lido
- **THEN** é lançada `OfxInvalido` mencionando o caminho

#### Scenario: Leitura sem estratégias
- **WHEN** o `LeitorOfx` é criado com `new ProcessadorExtrato([])`
- **THEN** `ler()` devolve o `Extrato` exatamente como montado a partir do arquivo

### Requirement: Dados brutos do arquivo
O `LeitorOfx` MUST montar um `OfxBruto` (`final readonly`) com o fuso configurado, os valores das tags anteriores à primeira `<STMTTRN>` e um método `transacoes()` que produz, por generator e sob demanda como `Extrato::transacoes`, os valores das tags de cada transação na ordem do arquivo. Os valores MUST estar em UTF-8, sem espaços nas pontas e com entidades decodificadas, sem nenhuma outra conversão. O `OfxBruto` MUST NOT fazer parte do `Extrato`.

#### Scenario: Data malformada preservada
- **WHEN** o arquivo tem `<DTSERVER>2024051315034[-3:GMT]`
- **THEN** `dataGeracao` do `Extrato` montado é `null` e o `OfxBruto` contém `DTSERVER` com o valor `"2024051315034[-3:GMT]"`

#### Scenario: Tags que não viram campo do Extrato
- **WHEN** o arquivo tem `<BRANCHID>0001` e `<ORG>CCPI PROGRESSO`
- **THEN** o `OfxBruto` contém `BRANCHID` e `ORG` com esses valores

### Requirement: Dados da transação
Cada `Transacao` MUST conter `fingerprint`, `tipo` (TRNTYPE), `valor` (TRNAMT em centavos), `data` (DTPOSTED), `nome` (NAME) e `memo` (MEMO). `tipo`, `nome` e `memo` MUST ser repassados como vieram, sem interpretação, apenas com espaços removidos das pontas e entidades decodificadas. `nome` MUST ser `null` quando a tag não existir ou estiver vazia; `memo` MUST ser string vazia quando a tag não existir.

#### Scenario: Banco que só usa MEMO
- **WHEN** a transação tem `<MEMO>RECEBIMENTO PIX` e não tem `<NAME>`
- **THEN** `memo` é `"RECEBIMENTO PIX"` e `nome` é `null`

#### Scenario: Banco que usa NAME e MEMO
- **WHEN** a transação tem `<MEMO>CRÉD.TED-STR` e `<NAME>AKAD SEGUROS S A 14.868.712 0001 `
- **THEN** `memo` é `"CRÉD.TED-STR"` e `nome` é `"AKAD SEGUROS S A 14.868.712 0001"`

#### Scenario: Tipo fora da especificação
- **WHEN** a transação tem `<TRNTYPE>QUALQUERCOISA`
- **THEN** `tipo` é `"QUALQUERCOISA"`, sem erro

#### Scenario: Linhas de saldo do banco
- **WHEN** o arquivo contém uma transação com `<MEMO>SALDO DO DIA`
- **THEN** ela é devolvida como uma `Transacao` comum

#### Scenario: Entidades no texto
- **WHEN** o memo é `PAGTO A&amp;B LTDA`
- **THEN** `memo` é `"PAGTO A&B LTDA"`

### Requirement: Formatos de arquivo tolerados
O `LeitorOfx` MUST ler OFX 1.x em SGML independentemente de: presença ou ausência de tags de fechamento nos elementos folha, quebras de linha CRLF, LF ou misturadas, tags indentadas, espaços após os dois-pontos nas linhas do cabeçalho (`VERSION: 102`) e linhas em branco entre o cabeçalho e `<OFX>`.

#### Scenario: Elementos sem tag de fechamento
- **WHEN** o arquivo contém `<TRNAMT>-3408.06` seguido de quebra de linha e da próxima tag, sem `</TRNAMT>`
- **THEN** o valor é lido como `-340806`

#### Scenario: Cabeçalho com espaços e tags indentadas
- **WHEN** o arquivo começa com `VERSION: 102` e `ENCODING: UTF - 8` e as tags estão indentadas
- **THEN** o arquivo é lido normalmente

### Requirement: Detecção de codificação
O `LeitorOfx` MUST ignorar a codificação declarada no cabeçalho (`ENCODING`/`CHARSET`) e detectá-la pelo conteúdo de cada valor, sem ler o arquivo inteiro antes: se os bytes do valor forem UTF-8 válido, MUST usá-los como estão; caso contrário, MUST convertê-los de Windows-1252 para UTF-8. Um BOM UTF-8 no início da origem MUST ser descartado. Todo texto devolvido MUST ser UTF-8 válido. Texto que já vem corrompido pelo banco (dupla codificação) MUST ser repassado como está.

#### Scenario: Arquivo em Windows-1252 declarado como USASCII
- **WHEN** o arquivo declara `ENCODING:USASCII` e contém o byte `0xC9` em `CRÉD.TED-STR`
- **THEN** `memo` é a string UTF-8 `"CRÉD.TED-STR"`

#### Scenario: Arquivo em UTF-8 declarado como CHARSET 1252
- **WHEN** o arquivo declara `CHARSET:1252` e contém `APLICAÇÃO` em UTF-8
- **THEN** `memo` contém `"APLICAÇÃO"` sem dupla codificação

### Requirement: Conversão de valores
`valor` MUST ser um `int` em centavos, com sinal. O valor MUST aceitar ponto ou vírgula como separador decimal, uma ou duas casas decimais, nenhuma casa decimal e sinal `+` ou `-` opcional. Qualquer outro formato MUST lançar `OfxInvalido`.

#### Scenario: Separador ponto
- **WHEN** `<TRNAMT>-3408.06`
- **THEN** `valor` é `-340806`

#### Scenario: Separador vírgula
- **WHEN** `<TRNAMT>3337,64`
- **THEN** `valor` é `333764`

#### Scenario: Uma casa decimal
- **WHEN** `<TRNAMT>12.5`
- **THEN** `valor` é `1250`

#### Scenario: Valor malformado
- **WHEN** `<TRNAMT>1.234,56`
- **THEN** é lançada `OfxInvalido` indicando a tag e o valor ao percorrer essa transação

### Requirement: Conversão de datas e fuso horário
O `LeitorOfx` MUST aceitar um `DateTimeZone` opcional no construtor; quando omitido, MUST usar `date_default_timezone_get()`. As datas MUST aceitar os formatos `AAAAMMDD`, `AAAAMMDDHHMMSS`, `AAAAMMDDHHMMSS.XXX`, cada um opcionalmente seguido de `[deslocamento:NOME]` (deslocamento com ou sem sinal, inteiro ou fracionário), e ISO 8601 (`AAAA-MM-DDTHH:MM:SS[.XXX]Z` ou com deslocamento), com espaços nas pontas ignorados. Quando a data informar deslocamento, ele MUST ser respeitado e o nome do fuso ignorado; quando não informar, a data MUST ser interpretada no fuso configurado. Toda data devolvida MUST ser `DateTimeImmutable` convertida para o fuso configurado.

DTSERVER, DTSTART e DTEND fora desses formatos, zerados ou inexistentes no calendário MUST virar `null` no `Extrato` montado, sem exceção, mantendo o valor original no `OfxBruto`. DTPOSTED nessas condições MUST lançar `OfxInvalido`.

#### Scenario: Nome de fuso inconsistente
- **WHEN** `<DTPOSTED>20231004100000[-03:EST]` é lido com fuso `America/Sao_Paulo`
- **THEN** `data` é `2023-10-04 10:00:00` em `America/Sao_Paulo` (o deslocamento `-03` prevalece sobre `EST`)

#### Scenario: Data sem fuso
- **WHEN** `<DTPOSTED>20230329120000` é lido com fuso `America/Sao_Paulo`
- **THEN** `data` é `2023-03-29 12:00:00` em `America/Sao_Paulo`

#### Scenario: Só a data
- **WHEN** `<DTPOSTED>20240628` ou `<DTPOSTED>20240502 `
- **THEN** a data é lida com hora `00:00:00` no fuso configurado

#### Scenario: ISO 8601
- **WHEN** `<DTSERVER>2026-04-09T18:30:59.298Z` é lido com fuso `America/Sao_Paulo`
- **THEN** `dataGeracao` é `2026-04-09 15:30:59` em `America/Sao_Paulo`

#### Scenario: Fuso padrão do PHP
- **WHEN** o `LeitorOfx` é criado sem argumentos e `date_default_timezone_get()` é `UTC`
- **THEN** todas as datas são devolvidas em `UTC`

#### Scenario: Data do extrato malformada
- **WHEN** `<DTSERVER>2024051315034[-3:GMT]` (13 dígitos, sem zeros à esquerda)
- **THEN** `dataGeracao` do `Extrato` montado é `null`

#### Scenario: Data do extrato zerada
- **WHEN** `<DTSERVER>00000000000000`
- **THEN** `dataGeracao` do `Extrato` montado é `null` (e não `-0001-11-30`)

#### Scenario: Data inexistente no calendário
- **WHEN** `<DTSTART>20230231`
- **THEN** `dataInicio` do `Extrato` montado é `null`

#### Scenario: Data de transação malformada
- **WHEN** `<DTPOSTED>2024051315034`
- **THEN** é lançada `OfxInvalido` indicando a tag e o valor ao percorrer essa transação

### Requirement: Rejeição de arquivos inválidos
O `LeitorOfx` MUST lançar `OfxInvalido` na leitura quando: a origem não contiver `<OFX>`; não houver `<STMTRS>` (extrato de conta corrente); ou faltar a tag BANKID, ACCTID, DTSTART, DTEND ou DTSERVER. Durante a iteração de `transacoes`, MUST lançar `OfxInvalido` quando uma transação não tiver TRNTYPE, DTPOSTED ou TRNAMT, tiver valor ou data inválidos, ou quando houver outro `<STMTRS>` ou `<CCSTMTRS>` no arquivo. A mensagem MUST identificar o motivo e, quando houver, a tag e o valor.

#### Scenario: Conteúdo que não é OFX
- **WHEN** o conteúdo é um PDF ou texto qualquer
- **THEN** é lançada `OfxInvalido`

#### Scenario: Extrato de cartão de crédito
- **WHEN** o arquivo contém `<CCSTMTRS>` e nenhum `<STMTRS>`
- **THEN** é lançada `OfxInvalido` na leitura

#### Scenario: Mais de um extrato no arquivo
- **WHEN** o arquivo contém dois `<STMTRS>`
- **THEN** a leitura devolve o `Extrato` do primeiro, e percorrer `transacoes` lança `OfxInvalido` ao chegar no segundo

#### Scenario: Campo obrigatório ausente
- **WHEN** o arquivo não contém a tag `<DTSERVER>`
- **THEN** é lançada `OfxInvalido` mencionando `DTSERVER`

