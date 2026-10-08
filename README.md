# inovartecnologia/ofx-simple-parser

Leitor de arquivos OFX 1.x (SGML) para extratos de conta corrente, em PHP 8.4, sem dependências de runtime.

## Instalação

```bash
composer require inovartecnologia/ofx-simple-parser
```

## Uso

```php
use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\ProcessadorExtrato;

$leitor = new LeitorOfx(
    fuso: new DateTimeZone('America/Sao_Paulo'),
    processador: ProcessadorExtrato::padrao(), // opcional
);

$extrato = $leitor->ler($conteudo);
// ou: $leitor->lerArquivo('/caminho/extrato.ofx');
// ou: $leitor->lerStream($resource);

foreach ($extrato->transacoes as $transacao) {
    // fingerprint, tipo, valor (centavos), data, nome, memo
}
```

As transações são produzidas **sob demanda** (generator). Erros de transação (valor ou data inválidos, campos faltando) lançam `OfxInvalido` durante o `foreach`, depois de produzir as transações anteriores. Envolva a importação numa transação de banco de dados se precisar de atomicidade.

Com `lerStream()`, mantenha o stream aberto enquanto percorrer `transacoes`.

Para obter o extrato sem correções por banco:

```php
$leitor = new LeitorOfx(processador: new ProcessadorExtrato([]));
```

## Estratégias

O pacote inclui, por padrão:

- **DatasSantander** — datas do cabeçalho sem zeros à esquerda (banco 33)
- **DatasBradesco** — `DTSERVER` zerado e período substituído pelas transações (banco 237)

Implemente `Estrategia` (`aceita`, `processa`) e passe uma lista ordenada a `ProcessadorExtrato`.

## Fingerprint

`sha256("{codigoBanco}|{conta}|{dia}|{valor}|{ordinal}")`, com o dia escrito no `DTPOSTED`, independente de fuso, memo ou FITID.

## O que o pacote não faz

- Cartão de crédito (`CCSTMTRS`), saldo, agência no `Extrato`
- Filtrar linhas "SALDO" do Itaú ou corrigir texto com dupla codificação do banco
- Regras de negócio do consumidor

## Testes

Requer PHP 8.4, [Pest](https://pestphp.com/) 5 e PCOV ou Xdebug (para TIA e cobertura na mutação).

```bash
composer test          # stubs + amostra convertida (tests/Fixtures/ofx/)
composer test:ci       # CI, suíte default
composer test:amostra  # só o smoke test da amostra convertida
composer test:exemplos # corpus completo em exemplos/ (local, se existir)
composer test:mutate   # mutation testing (Pest Mutate, paralelo)
composer test:coverage # cobertura de src/ (falha abaixo de 90%; Pest --coverage --min=90)
```

O CI em `.github/workflows/tests.yml` roda só `test:coverage` (PCOV, mínimo **90%** — já inclui a suíte de testes).
```

A **amostra convertida** são os 47 stubs anonimizados gerados por `scripts/gerar-stubs.py` a partir de `exemplos/`, versionados em `tests/Fixtures/ofx/` com `manifest.json`. O corpus bruto em `exemplos/` não entra no repositório.

Com repositório Git e PCOV/Xdebug, dá para usar TIA: `pest --parallel --tia --testsuite=default`.

Cada arquivo de teste (exceto amostra) chama `mutates(...alvosMutacao())`. O score mínimo está em `tests/Pest.php` (50% hoje; baseline atual ~52%).

## Licença

MIT
