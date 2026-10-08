## Context

O pacote substitui o fork interno do `asgrim/ofxparser` (namespace `OfxParser\`) usado pelo Simplifique. O desenho foi guiado por uma amostra local de 9.883 arquivos (8.964 únicos, 436.154 transações, 81 contas, 10 bancos) em `exemplos/`, inventariada por `scripts/inventario.py`.

O que a amostra mostrou:

- Todos os arquivos são OFX 1.02 SGML, de conta corrente, com exatamente um `<STMTRS>`.
- A codificação declarada no cabeçalho não é confiável: 1.847 arquivos declaram `USASCII` e são Windows-1252, e 165 declaram `USASCII` e são UTF-8.
- Valores usam ponto ou vírgula decimal (Bradesco e Santander usam vírgula); 331 têm uma casa decimal.
- As datas aparecem com fuso (`[-03:EST]`, com nome errado), sem fuso, só com o dia, com espaço sobrando e em ISO 8601 (Sicoob).
- O Santander grava DTSERVER sem zeros à esquerda em 193 arquivos (ex.: `2024051315034`), repetindo esse valor em DTSTART/DTEND em 24 deles.
- O Bradesco grava DTSERVER zerado (`00000000000000`) em 100% dos 1.251 arquivos e coloca o dia da exportação em DTSTART e DTEND, em vez do período das transações.
- O BANKID é sempre o código Bacen/COMPE do banco, com zeros à esquerda variáveis (`0341`, `033`, `756`); como inteiro, identifica o banco com segurança.
- O FITID não é único (Santander repete e usa `00000000`), e o texto das transações muda entre exportações (o banco enriquece o memo depois).

O parser atual lê tudo sem exceção, mas descarta o fuso, devolve `float` e corrompe arquivos UTF-8 com `utf8_encode()`.

Restrições: PHP 8.4, nenhuma dependência de runtime (nem extensões além do núcleo), classes e propriedades em português, nenhuma regra de negócio no leitor.

## Goals / Non-Goals

**Goals:**
- `LeitorOfx::ler(string)`, `lerArquivo(string $caminho)` e `lerStream($stream)` devolvendo `Extrato`, com `Extrato` e `Transacao` imutáveis.
- Memória constante: arquivo lido em streaming e transações produzidas por generator, sem lista em memória.
- Ler todos os 8.964 arquivos únicos da amostra sem exceção.
- Separar o leitor (só formato) das correções específicas de banco (estratégias), com as do Santander e do Bradesco ativas por padrão.
- Fingerprint estável entre exportações sobrepostas.
- Texto sempre em UTF-8 válido, valores em centavos `int`, datas com fuso.

**Non-Goals:**
- Cartão de crédito (`CCSTMTRS`), investimentos e OFX 2.x em XML (nenhum na amostra).
- Saldo (LEDGERBAL), agência, tipo de conta, moeda, CHECKNUM, REFNUM e FITID no `Extrato` (continuam acessíveis às estratégias pelo `OfxBruto`).
- Regras de negócio do consumidor: filtrar linhas "SALDO" do Itaú, deduplicar, corrigir texto com dupla codificação gerado pelo banco.
- Migração do Simplifique.

## Decisions

### PHP puro em vez de CLI em Rust
A ideia inicial era um CLI em Rust com o PHP como wrapper. Medido na amostra: o parse em PHP leva ~0,1 a 2,4 ms por arquivo, e só iniciar um processo com `proc_open` custa ~0,7 ms, antes de serializar e decodificar JSON. Rust não traria ganho perceptível e exigiria distribuir binários por plataforma via Composer.

### Leitor por tokens em streaming, em vez de converter SGML para XML
O parser atual transforma SGML em XML e usa SimpleXML; isso quebra com `&` solto (45 ocorrências na amostra), exige `ext-simplexml`/`ext-libxml` e carrega o arquivo e todas as transações na memória. O `LeitorOfx` lê a origem token a token com `stream_get_line($stream, 0, '<')`: cada token é `TAG>valor` ou `/TAG>`, interpretado por uma expressão regular, sem montar árvore. O valor termina no próximo `<`, o que cobre arquivos com e sem tags de fechamento e não depende de quebras de linha. A memória usada não depende do tamanho do arquivo (o maior token da amostra tem 186 bytes; o maior arquivo, 344 KB).

Alternativas consideradas: árvore SGML completa (maior do que o necessário) e regex sobre o conteúdo inteiro (exige o arquivo todo em memória).

### Transações por generator
`Extrato::$transacoes` é um `Transacoes implements IteratorAggregate`, cujo `getIterator()` é um generator que lê da origem e produz uma `Transacao` por vez:

- **Leitura em duas fases:** `ler()` consome a origem só até a primeira `<STMTTRN>` (na amostra, BANKID, ACCTID, DTSERVER, DTSTART e DTEND sempre vêm antes dela) e guarda essa posição. Cada iteração começa nessa posição.
- **Reiterável e aninhável:** cada iteração guarda a própria posição e faz `fseek` antes de ler a próxima transação, então dois `foreach` sobre o mesmo extrato (inclusive um dentro do outro) não interferem.
- **Origens:** `ler(string)` usa `php://memory`; `lerArquivo()` abre com `fopen(..., 'rb')` e fecha no destrutor do objeto que guarda o stream; `lerStream()` usa o stream recebido quando ele permite `fseek` e, senão, copia para `php://temp` (memória até 2 MB, disco depois).
- **Erros durante a iteração:** transação inválida, segundo `<STMTRS>` ou `<CCSTMTRS>` lançam `OfxInvalido` no `foreach`, depois de as transações anteriores terem sido produzidas. Quem consome precisa tratar a importação como transacional se não quiser dados parciais. Alternativa considerada: uma passada de validação em `ler()`; preferiu-se não ler o arquivo duas vezes.
- **Fingerprint:** o contador de ordinais por `dia|valor` é o único estado que cresce com o arquivo; é um inteiro por par distinto, recriado a cada iteração.

### Codificação detectada por valor, sem `mbstring`
`preg_match('//u', $valor)` decide, para cada valor, se ele é UTF-8 válido (PCRE faz parte do núcleo); se não for, a conversão de Windows-1252 para UTF-8 usa uma tabela própria (bytes `0x80`–`0x9F` pela tabela do Windows-1252 e `0xA0`–`0xFF` pelo mesmo código Unicode), sem `mbstring` nem `iconv`. Detectar por valor dispensa ler o arquivo inteiro antes; como `<` é ASCII, um token nunca corta um caractere UTF-8 ao meio. Na amostra, a detecção por valor deu o mesmo resultado que a detecção pelo arquivo inteiro em todos os tokens com bytes não ASCII dos 8.964 arquivos. Texto com dupla codificação gerado pelo banco (`DISPONÃ\u0083Â\u008dVEL`) é UTF-8 válido e passa sem alteração.

### Entidades e espaços
Só as entidades `&amp;`, `&lt;`, `&gt;`, `&quot;`, `&apos;` e as numéricas (`&#NN;`, `&#xNN;`) são decodificadas; `&` solto fica como está. Espaços nas pontas de todos os valores são removidos (há espaços sobrando em memos do Itaú, Sicredi, C6 e Banco do Brasil e em nomes do Sicoob).

### Valores em centavos sem passar por float
`/^([+-]?)(\d+)(?:[.,](\d{1,2}))?$/` sobre o valor sem espaços. Centavos = `inteiro * 100 + decimais completados à direita com zero`. Qualquer outro formato lança `OfxInvalido`, inclusive separador de milhar, porque não aparece na amostra e ler errado seria pior do que rejeitar.

### Datas: formatos explícitos e fuso de saída configurado
São aceitos `AAAAMMDD`, `AAAAMMDDHHMMSS`, `AAAAMMDDHHMMSS.XXX`, cada um com `[deslocamento:NOME]` opcional, e ISO 8601. Quando há deslocamento, ele vale (e o nome é ignorado: o Itaú escreve `-03:EST`); sem deslocamento, vale o fuso configurado. O resultado é sempre convertido para o fuso configurado, para que todas as datas do extrato estejam no mesmo fuso.

Datas do extrato (DTSERVER, DTSTART, DTEND) fora desses formatos, zeradas ou inexistentes no calendário viram `null`, e o valor original fica no `OfxBruto` para as estratégias. A validação de calendário precisa ser explícita: `DateTimeImmutable::createFromFormat` aceita `00000000` e devolve `-0001-11-30` sem erro. DTPOSTED nessas condições lança `OfxInvalido`, porque nenhuma transação da amostra tem esse problema e uma transação sem data não tem fingerprint.

Alternativa considerada e abandonada: lançar `OfxInvalido` para as datas malformadas do Santander. Isso rejeitaria 193 arquivos, e aplicada à data zerada do Bradesco rejeitaria todos os 1.251.

### Leitor cru + pipeline de estratégias
O OFX é um padrão que cada banco interpreta à sua maneira. Em vez de espalhar exceções por banco no leitor, o `LeitorOfx` só converte formato e entrega o resultado a um `ProcessadorExtrato`:

```
LeitorOfx::ler($conteudo)
  ├─ monta Extrato (cru) + OfxBruto (valores originais das tags e fuso configurado)
  └─ ProcessadorExtrato: para cada Estrategia, em ordem
        aceita($extrato, $bruto)?  não → segue com o mesmo extrato
                                   sim → $extrato = processa($extrato, $bruto)
```

```php
interface Estrategia
{
    public function aceita(Extrato $extrato, OfxBruto $bruto): bool;
    public function processa(Extrato $extrato, OfxBruto $bruto): Extrato;
}
```

- **Pipeline, não "primeira que aceitar":** uma estratégia de datas e outra de, por exemplo, texto podem atuar no mesmo extrato sem precisar saber uma da outra. A ordem da lista é a ordem de execução.
- **`OfxBruto` separado do `Extrato`:** o `Extrato` público continua só com os campos decididos; o que uma estratégia precisa reinterpretar (o texto original de DTSERVER, BRANCHID, ORG etc.) chega por fora. O `OfxBruto` também carrega o fuso configurado, para que a estratégia converta datas como o leitor, e oferece `transacoes()` (generator com os valores crus de cada transação, lido da mesma origem).
- **Estratégias que precisam das transações:** `DatasSantander` (dia da última transação) e `DatasBradesco` (primeira e última data) percorrem `OfxBruto::transacoes()` uma vez dentro de `ler()`, guardando só o mínimo e o máximo. Nesses arquivos, a origem é lida duas vezes, e um DTPOSTED inválido aparece já na leitura.
- **Identificação pelo código Bacen:** as estratégias decidem `aceita` pelo `codigoBanco` (já normalizado como inteiro) e pelo sintoma (data `null`), nunca só pelo banco. Assim, se o banco corrigir o arquivo, a estratégia deixa de atuar sozinha.
- **Imutabilidade:** `Extrato` ganha um método `com(...)` com argumentos nomeados que devolve uma cópia alterada (o PHP 8.4 ainda não tem `clone with`).
- **Configuração:** `new LeitorOfx(fuso: ..., processador: ...)`. Sem processador, usa `ProcessadorExtrato::padrao()` (`DatasSantander`, `DatasBradesco`); `new ProcessadorExtrato([])` devolve o extrato cru.
- **Conversão de datas compartilhada:** a conversão com deslocamento/fuso sai do `LeitorOfx` para uma classe interna usada também pelas estratégias.

### DatasSantander
Para cada data `null` com valor original de 10 a 13 dígitos: lista as divisões dos dígitos após o ano em mês, dia, hora, minuto e segundo (1 ou 2 dígitos cada) que existem no calendário; se caírem em mais de um dia, descarta os dias anteriores à última transação (o arquivo não pode ter sido gerado antes dela) e fica com o menor; se o dia tiver uma só leitura, usa o instante, senão usa o dia às 00:00. Na amostra (193 arquivos):

| Situação do DTSERVER | Arquivos |
|---|---|
| Uma única leitura | 90 |
| Um só dia, horário ambíguo | 22 |
| Mais de um dia, resolvido pela última transação | 59 |
| Mais de um dia mesmo depois da última transação (fica o menor) | 22 |

Nos 31 arquivos cujo nome registra o momento do upload, o dia escolhido é o dia do upload em todos, inclusive nos 9 que precisaram ficar com o menor dia.

### DatasBradesco
Aceita quando o banco é 237 e `dataGeracao` é `null`. Preenche `dataGeracao` com `dataFim`: em 95% dos arquivos cujo nome registra o upload, esse é o dia do upload. Substitui o período pelo dia da primeira e da última transação, porque DTSTART e DTEND são sempre o mesmo dia (o da exportação) e as transações começam antes dele em 1.245 de 1.246 arquivos com transações.

### Estratégias não alteram transações
As estratégias do pacote só tocam nas três datas do extrato. O fingerprint é calculado pelo leitor a partir do DTPOSTED e não depende delas, então ligar ou desligar estratégias nunca muda a identificação das transações.

### Fingerprint pelo dia escrito no arquivo
`hash('sha256', "{banco}|{conta}|{dia}|{valor}|{ordinal}")`. O `dia` vem dos 8 primeiros dígitos de DTPOSTED (ou da parte de data do ISO 8601), não da data convertida: assim o fingerprint não muda quando o consumidor configura outro fuso. Texto, FITID e horário ficam de fora porque são instáveis entre exportações. Na amostra, essa composição gerou os mesmos fingerprints em 93,2% dos 9.891 dias cobertos por mais de uma exportação; com o memo incluído, seriam 77%. Os 6,8% restantes têm conteúdo realmente diferente (lançamentos que aparecem depois ou débitos divididos).

O ordinal conta só transações com o mesmo dia e valor; uma transação diferente no mesmo dia não desloca a numeração.

### Uma única exceção
`OfxInvalido extends \RuntimeException`, com mensagem em português indicando o motivo, a tag e o valor. Uma classe é suficiente porque o consumidor só precisa distinguir "arquivo rejeitado" de erro de programação.

### Estrutura do pacote

```
composer.json        inovartecnologia/ofx-simple-parser, MIT, php ^8.4, PSR-4 InovarTecnologia\OfxSimpleParser\ => src/
LICENSE              MIT
src/LeitorOfx.php
src/Extrato.php
src/Transacao.php
src/Transacoes.php
src/OfxBruto.php
src/OfxInvalido.php
src/ProcessadorExtrato.php
src/Estrategia.php
src/Estrategias/DatasSantander.php
src/Estrategias/DatasBradesco.php
tests/               PHPUnit
tests/Fixtures/ofx/  stubs anonimizados + manifest.json (scripts/gerar-stubs.py)
```

As partes internas do `LeitorOfx` (codificação, valores, datas) ficam como métodos privados enquanto couberem num arquivo legível; extrair classes só se crescerem.

### Testes em duas camadas
1. Testes unitários com trechos de OFX escritos no próprio teste, um por cenário das specs.
2. Testes com os 47 stubs de `tests/Fixtures/ofx/`, comparando quantidade de transações, soma dos valores e datas com o `manifest.json`, e os pares sobrepostos (`grupos`) para o fingerprint.

Uma suíte opcional percorre `exemplos/` quando a pasta existir (só na máquina local) para conferir a paridade com a amostra completa; ela não roda no CI.

## Risks / Trade-offs

- [Estratégias inferem dados que o banco não informou] → Cada inferência está documentada nos testes e é verificada contra a amostra; quem não quiser inferência usa `new ProcessadorExtrato([])` e recebe as datas `null`.
- [Desempate do Santander pelo menor dia pode errar] → Acertou os 9 casos verificáveis; os outros 13 arquivos ambíguos não têm data de upload no nome para conferir.
- [Importação parcial: o erro de uma transação só aparece no `foreach`] → Documentado no README; o consumidor deve envolver a importação numa transação de banco de dados ou percorrer uma vez antes de gravar.
- [Stream do consumidor fechado antes de percorrer as transações] → Erro de leitura no `foreach`; documentado que o stream precisa continuar aberto enquanto o `Extrato` for usado.
- [Arquivo com tags do extrato depois da primeira transação] → Não ocorre na amostra; nesse caso a tag é considerada ausente e a leitura lança `OfxInvalido`, o que torna o caso visível.
- [Datas do extrato opcionais no tipo, mesmo preenchidas na prática] → Com as estratégias padrão, nenhum arquivo da amostra termina com data `null`; o tipo nulo só aparece para bancos ou formatos ainda não vistos.
- [Novo formato de data ou valor em um banco ainda não visto] → O arquivo é rejeitado com mensagem clara em vez de produzir dado errado; o caso vira um novo stub e um novo cenário.
- [Fingerprint muda se o banco alterar dia ou valor de uma transação entre exportações] → Limitação inerente; documentada no README como "estável quando o conjunto do dia não muda".
- [Ordinal depende da ordem do arquivo] → Só afeta transações com mesmo dia e mesmo valor; na amostra, nenhuma divergência veio da ordem.
- [Texto com dupla codificação gerado pelo banco chega ao consumidor] → Fora do escopo por decisão (sem inteligência); o texto é UTF-8 válido, só não é o texto original.
- [Nomes e sobrenomes comuns sobrevivem à anonimização dos stubs] → Números são sempre trocados; revisar a lista de palavras públicas antes de tornar o repositório público e subir `PUBLIC_MIN_ACCOUNTS` se necessário.

## Migration Plan

Pacote novo, sem migração própria. Como o namespace não colide com `OfxParser\`, o Simplifique pode instalar os dois e comparar as saídas antes de trocar.

## Open Questions

- Versão do PHPUnit e se o projeto usa alguma ferramenta de estilo (o fork usa `phpcs`).
- Unicidade da conta no fingerprint: o Sicoob envia contas curtas (`999-9`) que só são únicas junto com a agência (BRANCHID), e o Bradesco envia contas de 4 a 6 dígitos sem agência. Duas contas de agências diferentes do mesmo banco podem gerar o mesmo fingerprint.
