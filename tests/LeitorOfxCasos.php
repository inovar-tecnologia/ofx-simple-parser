<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

mutates(...alvosMutacao());

test('rejeita extrato de cartao sem conta corrente', function () {
    $ofx = <<<'OFX'
<OFX>
<CCSTMTRS>
<BANKID>341
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240101000000
<TRNAMT>1.00
</STMTTRN>
</CCSTMTRS>
</OFX>
OFX;

    (new LeitorOfx)->ler($ofx);
})->throws(OfxInvalido::class);

test('rejeita campo obrigatorio ausente no cabecalho', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240101000000
<TRNAMT>1.00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    (new LeitorOfx)->ler($ofx);
})->throws(OfxInvalido::class);

test('rejeita transacao com TRNAMT vazio', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<BANKID>341
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240101000000
<TRNAMT>   
<MEMO>x
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $extrato = (new LeitorOfx)->ler($ofx);

    iterator_to_array($extrato->transacoes);
})->throws(OfxInvalido::class);

test('transacao sem MEMO usa string vazia', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<BANKID>341
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240101000000
<TRNAMT>1.00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $extrato = (new LeitorOfx)->ler($ofx);

    expect(primeiraTransacao($extrato)->memo)->toBe('');
});

test('lerArquivo inexistente', function () {
    (new LeitorOfx)->lerArquivo(__DIR__.'/Fixtures/ofx/nao-existe.ofx');
})->throws(OfxInvalido::class);

test('lerStream invalido', function () {
    (new LeitorOfx)->lerStream('nao-e-resource');
})->throws(OfxInvalido::class);

test('BOM no inicio do arquivo', function () {
    $extrato = (new LeitorOfx)->ler("\xEF\xBB\xBF".ofxMinimo('2.00'));

    expect(primeiraTransacao($extrato)->valor)->toBe(200);
});

test('Santander com data malformada no cabecalho', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<BANKID>033
<ACCTID>1
<DTSTART>2024051315034[-3:GMT]
<DTEND>2024051315034[-3:GMT]
<DTSERVER>2024051315034[-3:GMT]
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240514120000
<TRNAMT>10,00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $extrato = (new LeitorOfx(new DateTimeZone('America/Sao_Paulo')))->ler($ofx);

    expect($extrato->codigoBanco)->toBe(33)
        ->and($extrato->dataGeracao)->not->toBeNull()
        ->and($extrato->dataInicio)->not->toBeNull();
});

test('rejeita segundo bloco STMTRS no meio das transacoes', function () {
    $ofx = <<<'OFX'
<OFX>
<STMTRS>
<BANKID>341
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240101000000
<TRNAMT>1.00
<STMTRS>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240102000000
<TRNAMT>2.00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $extrato = (new LeitorOfx)->ler($ofx);

    iterator_to_array($extrato->transacoes);
})->throws(OfxInvalido::class);
