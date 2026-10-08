<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\FingerprintOfx;

mutates(FingerprintOfx::class, ...alvosMutacao());

test('valor de referencia do fingerprint', function () {
    $esperado = hash('sha256', '341|1000550002|2023-09-28|-340806|1');

    expect(FingerprintOfx::gerar(341, '1000550002', '2023-09-28', -340806, 1))->toBe($esperado);
});
