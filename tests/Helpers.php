<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Extrato;
use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\ProcessadorExtrato;
use InovarTecnologia\OfxSimpleParser\Transacao;

/**
 * @return list<class-string>
 */
function alvosMutacao(): array
{
    return [
        LeitorOfx::class,
        Extrato::class,
        Transacao::class,
        \InovarTecnologia\OfxSimpleParser\Transacoes::class,
        \InovarTecnologia\OfxSimpleParser\OfxBruto::class,
        \InovarTecnologia\OfxSimpleParser\OfxInvalido::class,
        ProcessadorExtrato::class,
        \InovarTecnologia\OfxSimpleParser\Estrategias\DatasSantander::class,
        \InovarTecnologia\OfxSimpleParser\Estrategias\DatasBradesco::class,
        \InovarTecnologia\OfxSimpleParser\Interno\DatasMalformadas::class,
        \InovarTecnologia\OfxSimpleParser\Interno\OrigemOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\DataOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\FingerprintOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\TokenOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\ValorOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\TextoOfx::class,
        \InovarTecnologia\OfxSimpleParser\Interno\TagOfx::class,
    ];
}

function lerOfxMinimo(string $conteudo): Extrato
{
    return (new LeitorOfx(new DateTimeZone('America/Sao_Paulo'), new ProcessadorExtrato([])))->ler($conteudo);
}

function primeiraTransacao(Extrato $extrato): Transacao
{
    return iterator_to_array($extrato->transacoes)[0];
}

function ofxMinimo(string $trnamt, string $memo = 'TESTE', string $extraTransacao = ''): string
{
    return <<<OFX
OFXHEADER:100
<OFX>
<STMTRS>
<BANKID>341
<ACCTID>1000550002
<DTSTART>20230928000000
<DTEND>20230928000000
<DTSERVER>20230928100000[-03:EST]
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20230928100000[-03:EST]
<TRNAMT>{$trnamt}
<MEMO>{$memo}
</STMTTRN>
{$extraTransacao}
</STMTRS>
</OFX>
OFX;
}

function blocoTransacaoOfx(string $trnamt): string
{
    return <<<OFX
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20230928100000[-03:EST]
<TRNAMT>{$trnamt}
<MEMO>OUTRA
</STMTTRN>
OFX;
}

/**
 * @return iterable<string, array{array<string, mixed>}>
 */
function manifestoStubs(): iterable
{
    $manifest = json_decode(
        file_get_contents(__DIR__.'/Fixtures/ofx/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($manifest as $entrada) {
        yield $entrada['arquivo'] => [$entrada];
    }
}
