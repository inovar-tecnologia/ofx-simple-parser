## Why

O Simplifique lê extratos OFX com um fork interno do `asgrim/ofxparser`, que chama `utf8_encode()` em todo arquivo (deprecated desde o PHP 8.2 e que corrompe os arquivos já em UTF-8), descarta o fuso horário das datas e devolve valores como `float`. Além disso, o FITID dos bancos não identifica transações de forma confiável (o Santander repete FITIDs no mesmo arquivo e usa `00000000`), então não há um identificador estável para importações que se sobrepõem.

## What Changes

- Novo pacote Composer `inovartecnologia/ofx-simple-parser`, licença MIT, PHP 8.4, sem dependências, namespace `InovarTecnologia\OfxSimpleParser\`.
- Classe `LeitorOfx` que lê um arquivo OFX a partir do conteúdo, de um caminho ou de um stream, em streaming, monta um `Extrato` "cru" e o entrega a um `ProcessadorExtrato`; o fuso horário é configurável e, por padrão, é o do PHP (`date_default_timezone_get()`).
- VO `Extrato` com código do banco, número da conta, data de início, data de fim, data de geração (as três datas opcionais) e as transações, produzidas sob demanda por generator (`Transacoes`), sem lista em memória.
- VO `Transacao` com fingerprint, tipo, valor em centavos (`int`), data, nome e memo.
- VO `OfxBruto` com o valor original de cada tag, para que as estratégias possam reinterpretar o que o leitor não conseguiu converter.
- `ProcessadorExtrato`: executa, em ordem, uma lista de `Estrategia`; cada estratégia decide se aceita o extrato (`aceita`) e, se aceitar, devolve um novo `Extrato` (`processa`) para a seguinte.
- Estratégias incluídas e ativas por padrão: `DatasSantander` (datas sem zeros à esquerda) e `DatasBradesco` (data de geração zerada e período que não corresponde às transações).
- Fingerprint determinístico por transação (banco, conta, dia, valor e ordinal entre transações idênticas), estável entre exportações sobrepostas da mesma conta.
- Leitura tolerante às variações encontradas em 8.964 arquivos reais de 10 bancos: SGML com e sem tags de fechamento, cabeçalho com espaços, tags indentadas, CRLF/LF misturados, codificação real diferente da declarada (ASCII, Windows-1252, UTF-8), vírgula ou ponto decimal, datas com fuso, sem fuso, só com o dia, com espaço sobrando ou em ISO 8601.
- O leitor não tem regra de negócio: os dados saem como vieram (linhas "SALDO" do Itaú, FITIDs repetidos, texto com dupla codificação gerado pelo banco), exceto pelas conversões de tipo de valor e data. Toda interpretação específica de banco fica nas estratégias.
- Fixtures anonimizadas em `tests/Fixtures/ofx/`, geradas por `scripts/gerar-stubs.py` a partir de `exemplos/` (que nunca é versionado), com `manifest.json` de valores esperados.

## Capabilities

### New Capabilities

- `leitura-ofx`: leitura de um arquivo OFX de conta corrente para os VOs `Extrato` e `Transacao`, incluindo detecção de codificação, conversão de valores para centavos, conversão de datas com fuso configurável e tolerância às variações de formato dos bancos.
- `fingerprint-transacao`: geração de um identificador determinístico e reproduzível para cada transação, independente do FITID e do texto da transação.
- `estrategias-banco`: pipeline de estratégias que corrigem o `Extrato` conforme as particularidades de cada banco, identificado pelo código Bacen, incluindo as estratégias de datas do Santander e do Bradesco.

### Modified Capabilities

(nenhuma; o projeto ainda não tem specs)

## Impact

- Código novo: `composer.json`, `src/` (`LeitorOfx`, `Extrato`, `Transacao`, `OfxBruto`, `ProcessadorExtrato`, `Estrategia`, `Estrategias\` e a exceção), `tests/`.
- Sem dependências de runtime; dependências de desenvolvimento apenas para testes (PHPUnit).
- Ferramentas já existentes no repositório: `scripts/inventario.py` e `scripts/gerar-stubs.py`.
- Consumidor previsto: o Simplifique, que hoje usa o fork interno do `asgrim/ofxparser` (namespace `OfxParser\`); os dois pacotes podem coexistir durante a migração. A migração do Simplifique não faz parte desta mudança.
