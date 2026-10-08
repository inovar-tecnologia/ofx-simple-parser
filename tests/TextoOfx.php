<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\TextoOfx;

mutates(...alvosMutacao());

test('remove BOM UTF-8', function () {
    $semBom = ofxMinimo('1.00');

    expect(TextoOfx::removerBom("\xEF\xBB\xBF".$semBom))->toBe($semBom);
});

test('decodifica entidades nomeadas e numericas', function () {
    expect(TextoOfx::normalizar('&lt;x&gt; &quot;a&quot; &#65; &#x42;'))
        ->toBe('<x> "a" A B');
});

test('converte bytes Windows-1252 para UTF-8', function () {
    $bytes = "\x80"; // euro em Windows-1252

    expect(TextoOfx::paraUtf8($bytes))->toBe('€');
});

test('entidade numerica fora do Unicode permanece literal', function () {
    expect(TextoOfx::normalizar('&#'.(0x10FFFF + 1).';'))->toBe('&#'.(0x10FFFF + 1).';');
});
