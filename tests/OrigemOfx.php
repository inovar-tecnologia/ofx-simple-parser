<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\OrigemOfx;
use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

mutates(...alvosMutacao());

test('copia stream nao seekable', function () {
    $conteudo = ofxMinimo('3.00');
    $processo = proc_open(
        ['php', '-r', 'echo stream_get_contents(STDIN);'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    expect($processo)->toBeResource();

    fwrite($pipes[0], $conteudo);
    fclose($pipes[0]);

    $extrato = (new LeitorOfx)->lerStream($pipes[1]);
    fclose($pipes[1]);
    proc_close($processo);

    expect(primeiraTransacao($extrato)->valor)->toBe(300);
});

test('rejeita CCSTMTRS apos inicio das transacoes', function () {
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
<CCSTMTRS>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20240102000000
<TRNAMT>2.00
</STMTTRN>
</STMTRS>
</OFX>
OFX;

    $origem = OrigemOfx::deString($ofx);
    $origem->definirOffsetPrimeiraTransacao(0);
    $generator = $origem->transacoesBrutas();
    $generator->current();
    $generator->next();
})->throws(OfxInvalido::class);
