## 1. Estrutura do pacote

- [x] 1.1 Criar `composer.json` (`inovartecnologia/ofx-simple-parser`, `"license": "MIT"`, `php: ^8.4`, sem `require` além do PHP, PSR-4 `InovarTecnologia\OfxSimpleParser\` → `src/`, `autoload-dev` para `tests/`, PHPUnit em `require-dev`)
- [x] 1.2 Criar `phpunit.xml.dist` com a suíte padrão e uma suíte separada `amostra` (fora do padrão) para `exemplos/`
- [x] 1.3 Rodar `composer install` e confirmar que `vendor/bin/phpunit` executa sem testes

## 2. Fixtures

- [x] 2.1 Ajustar `scripts/gerar-stubs.py` para registrar no `manifest.json` quais datas do extrato vêm malformadas ou zeradas (`"datasNulas": ["DTSERVER", ...]`), para os testes do leitor cru
- [x] 2.2 Regenerar os stubs e conferir que `santander-01`, `03`, `04`, `06`, `07` e `08` e todos os do Bradesco aparecem com `DTSERVER` em `datasNulas`

## 3. Value objects e exceção

- [x] 3.1 Criar `OfxInvalido extends \RuntimeException`
- [x] 3.2 Criar `Transacao` (`final readonly`: `fingerprint`, `tipo`, `valor`, `data`, `nome`, `memo`)
- [x] 3.3 Criar `Extrato` (`final readonly`: `codigoBanco`, `numeroConta`, `?dataInicio`, `?dataFim`, `?dataGeracao`, `transacoes` como `Transacoes`) com o método `com(...)` que devolve uma cópia alterada
- [x] 3.4 Criar `OfxBruto` (`final readonly`: `fuso`, valores das tags anteriores à primeira `<STMTTRN>` e `transacoes()` como generator dos valores crus de cada transação)

## 4. LeitorOfx: origem e texto

- [x] 4.1 Teste e implementação: construtor com `?DateTimeZone` (usando `date_default_timezone_get()` quando nulo) e `?ProcessadorExtrato` (usando `ProcessadorExtrato::padrao()` quando nulo)
- [x] 4.2 Teste e implementação: origens `ler(string)` (`php://memory`), `lerArquivo()` (`fopen 'rb'`, fechado no destrutor, `OfxInvalido` para caminho ilegível) e `lerStream()` (usa o stream com `fseek`, senão copia para `php://temp`; nunca fecha o stream do consumidor)
- [x] 4.3 Teste e implementação: tokenização em streaming com `stream_get_line(..., '<')`, cobrindo tags com e sem fechamento, CRLF/LF/misturado, indentação, cabeçalho com espaços e arquivo numa linha só
- [x] 4.4 Teste e implementação: rejeitar na leitura origem sem `<OFX>` ou sem `<STMTRS>` (incluindo arquivo só com `<CCSTMTRS>`)
- [x] 4.5 Teste e implementação: detecção de codificação por valor (UTF-8 válido usado como está; senão Windows-1252 → UTF-8 por tabela própria, sem `mbstring`/`iconv`) e BOM descartado no início da origem
- [x] 4.6 Teste e implementação: decodificação de `&amp;`, `&lt;`, `&gt;`, `&quot;`, `&apos;` e entidades numéricas, mantendo `&` solto; remoção de espaços nas pontas
- [x] 4.7 Teste e implementação: montar o `OfxBruto` com as tags anteriores à primeira `<STMTTRN>` e `transacoes()` sob demanda

## 5. LeitorOfx: valores e datas

- [x] 5.1 Teste e implementação: valor em centavos (ponto, vírgula, uma casa, sem casa, sinal) e `OfxInvalido` para outros formatos
- [x] 5.2 Teste e implementação: conversor de datas compartilhado (`AAAAMMDD`, `AAAAMMDDHHMMSS`, `.XXX`, `[deslocamento:NOME]` com e sem sinal e fracionário, ISO 8601 com `Z` ou deslocamento, espaços nas pontas)
- [x] 5.3 Teste e implementação: deslocamento do arquivo prevalece sobre o nome do fuso; sem deslocamento usa o fuso configurado; saída sempre convertida para o fuso configurado
- [x] 5.4 Teste e implementação: DTSERVER, DTSTART e DTEND malformados, zerados (`00000000000000`, sem virar `-0001-11-30`) ou inexistentes no calendário (`20230231`) viram `null`; DTPOSTED nessas condições lança `OfxInvalido`

## 6. LeitorOfx: extrato e transações

- [x] 6.1 Teste e implementação: montar o `Extrato` lendo só até a primeira `<STMTTRN>` (BANKID, ACCTID, DTSTART, DTEND, DTSERVER) e `OfxInvalido` quando alguma dessas tags faltar
- [x] 6.2 Teste e implementação: `Transacoes implements IteratorAggregate` com generator que produz cada `Transacao` (TRNTYPE, TRNAMT, DTPOSTED, NAME, MEMO), com `nome` nulo quando ausente ou vazio e `memo` vazio quando ausente
- [x] 6.3 Teste e implementação: `OfxInvalido` durante a iteração para transação sem TRNTYPE, DTPOSTED ou TRNAMT, valor ou data inválidos, segundo `<STMTRS>` ou `<CCSTMTRS>`, depois de produzir as transações anteriores
- [x] 6.4 Teste: iterar duas vezes e em `foreach` aninhado produz as mesmas transações
- [x] 6.5 Teste: arquivo sem transações não produz nenhuma `Transacao`; linhas "SALDO" e tipos fora da especificação passam sem alteração
- [x] 6.6 Teste de memória: stream gerado com 1.000.000 de transações (sem montar o arquivo em memória) percorrido inteiro, com pico de memória limitado independentemente da quantidade

## 7. Fingerprint

- [x] 7.1 Teste e implementação: `hash('sha256', "{banco}|{conta}|{dia}|{valor}|{ordinal}")` com banco sem zeros à esquerda, dia escrito no DTPOSTED e ordinal por dia + valor, com o contador recriado a cada iteração
- [x] 7.2 Teste: valor de referência da spec, transações idênticas com ordinais 1 e 2, transação diferente intercalada sem deslocar o ordinal
- [x] 7.3 Teste: mesmo fingerprint com fuso `UTC` e `America/Sao_Paulo`, e com memo ou FITID diferentes

## 8. Estratégias

- [x] 8.1 Criar a interface `Estrategia` (`aceita`, `processa`)
- [x] 8.2 Teste e implementação: `ProcessadorExtrato` em pipeline (ordem da lista, extrato alterado passado adiante, estratégia que não aceita não altera nada), `ProcessadorExtrato::padrao()` com `DatasSantander` e `DatasBradesco`, e `new ProcessadorExtrato([])` devolvendo o extrato cru
- [x] 8.3 Teste e implementação: `DatasSantander` com os cenários da spec (leitura única, horário ambíguo, desempate pela última transação, menor dia, período com o mesmo valor malformado, não aceita outro banco)
- [x] 8.4 Teste e implementação: `DatasBradesco` com os cenários da spec (com transações, sem transações, não aceita quando `dataGeracao` está preenchida)
- [x] 8.5 Teste: as estratégias do pacote não alteram `codigoBanco`, `numeroConta` nem `transacoes`, e percorrem `OfxBruto::transacoes()` no máximo uma vez

## 9. Testes com os stubs

- [x] 9.1 Teste com data provider sobre `tests/Fixtures/ofx/manifest.json`, lendo sem estratégias: conferir banco, conta, quantidade de transações, soma dos valores e as datas em `datasNulas` como `null`
- [x] 9.2 Teste dos stubs com as estratégias padrão: nenhuma data do extrato fica `null`
- [x] 9.3 Teste dos pares em `grupos` (`sobreposicao-*`): nos dias completos comuns às duas exportações, os conjuntos de fingerprints são iguais, exceto nos dias em que valores diferem entre os arquivos
- [x] 9.4 Confirmar que todo texto devolvido pelos stubs é UTF-8 válido

## 10. Verificação com a amostra completa (local)

- [x] 10.1 Suíte `amostra` que percorre `exemplos/` quando a pasta existir e é ignorada caso contrário
- [x] 10.2 Rodar a suíte: os 8.964 arquivos únicos lidos sem exceção, com 436.154 transações; com as estratégias padrão, nenhuma data do extrato `null`
- [x] 10.3 Conferir `DatasSantander` na amostra: a contagem por situação bate com a tabela do design e, nos arquivos com momento do upload no nome, o dia de `dataGeracao` é o dia do upload
- [x] 10.4 Comparar com o parser atual (fork interno do `asgrim/ofxparser`): mesma quantidade de transações, mesmos valores e mesmo dia por transação

## 11. Documentação

- [x] 11.1 README com instalação, uso (`ler`, `lerArquivo`, `lerStream`), formato do `Extrato` e da `Transacao`, transações sob demanda (erros no `foreach`, stream aberto enquanto o extrato for usado), configuração de fuso, estratégias (padrão, como desligar e como escrever uma nova), regras do fingerprint e suas limitações, e o que o pacote deliberadamente não faz
- [x] 11.2 Criar o arquivo `LICENSE` com o texto da licença MIT
