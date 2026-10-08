<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\LeitorOfx;

mutates(...alvosMutacao());

test('cinquenta mil transacoes com uso de memoria limitado', function () {
    $stream = fopen('php://temp', 'r+b');
    expect($stream)->toBeResource();

    fwrite($stream, <<<'OFX'
<OFX>
<STMTRS>
<BANKID>341
<ACCTID>1
<DTSTART>20240101000000
<DTEND>20240101000000
<DTSERVER>20240101000000

OFX);
    for ($i = 0; $i < 50_000; $i++) {
        fwrite($stream, "<STMTTRN>\n<TRNTYPE>CREDIT\n<DTPOSTED>20240101000000\n<TRNAMT>1.00\n<MEMO>x\n");
    }
    fwrite($stream, "</STMTRS>\n</OFX>\n");
    rewind($stream);

    gc_collect_cycles();
    $antes = memory_get_usage(true);

    $extrato = (new LeitorOfx(new DateTimeZone('UTC')))->lerStream($stream);
    $contagem = 0;
    foreach ($extrato->transacoes as $_) {
        $contagem++;
    }

    $depois = memory_get_usage(true);

    expect($contagem)->toBe(50_000)
        ->and($depois - $antes)->toBeLessThan(32 * 1024 * 1024);
});
