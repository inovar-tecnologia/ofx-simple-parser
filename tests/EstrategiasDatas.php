<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Estrategias\DatasBradesco;
use InovarTecnologia\OfxSimpleParser\Estrategias\DatasSantander;
use InovarTecnologia\OfxSimpleParser\Extrato;
use InovarTecnologia\OfxSimpleParser\Interno\OrigemOfx;
use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\OfxBruto;
use InovarTecnologia\OfxSimpleParser\ProcessadorExtrato;
use InovarTecnologia\OfxSimpleParser\Transacoes;

mutates(...alvosMutacao());

test('Bradesco retorna extrato quando dataFim ainda e null', function () {
    $fuso = new DateTimeZone('UTC');
    $origem = OrigemOfx::deString(<<<'OFX'
<OFX>
<STMTRS>
<BANKID>237
<ACCTID>1
<DTSTART>00000000
<DTEND>00000000
<DTSERVER>00000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240115120000
<TRNAMT>1.00
</STMTTRN>
</STMTRS>
</OFX>
OFX);
    $bruto = new OfxBruto($fuso, [], $origem);
    $extrato = new Extrato(
        codigoBanco: 237,
        numeroConta: '1',
        dataInicio: null,
        dataFim: null,
        dataGeracao: null,
        transacoes: new Transacoes(static fn (): \Generator => yield from []),
    );

    $resultado = (new DatasBradesco)->processa($extrato, $bruto);

    expect($resultado->dataGeracao)->toBeNull();
});

test('Santander nao aceita outros bancos', function () {
    $conteudo = file_get_contents(__DIR__.'/Fixtures/ofx/itau-01.ofx');
    $leitor = new LeitorOfx(new DateTimeZone('UTC'), new ProcessadorExtrato([]));
    $extrato = $leitor->ler($conteudo);
    $bruto = new OfxBruto(
        new DateTimeZone('UTC'),
        [],
        OrigemOfx::deString($conteudo),
    );

    expect((new DatasSantander)->aceita($extrato, $bruto))->toBeFalse();
});

test('Santander resolver retorna null para comprimento invalido', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<BANKID>033
<ACCTID>1
<DTSTART>20240513
<DTEND>20240513
<DTSERVER>20240513
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240514120000
<TRNAMT>1.00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $leitor = new LeitorOfx(new DateTimeZone('UTC'), new ProcessadorExtrato([]));
    $extrato = $leitor->ler($ofx);
    $bruto = new OfxBruto(new DateTimeZone('UTC'), [
        'DTSTART' => '20240513',
        'DTEND' => '20240513',
        'DTSERVER' => '20240513',
    ], OrigemOfx::deString($ofx));

    $processado = (new DatasSantander)->processa($extrato, $bruto);

    expect($processado->dataInicio)->not->toBeNull();
});
