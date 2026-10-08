<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

$pastaFixtures = dirname(__DIR__).'/Fixtures/ofx';
$pastaExemplos = dirname(__DIR__, 2).'/exemplos';

test('amostra convertida (stubs em tests/Fixtures/ofx)', function () use ($pastaFixtures) {
    $manifest = json_decode(
        file_get_contents($pastaFixtures.'/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $leitor = new LeitorOfx(new DateTimeZone('America/Sao_Paulo'));
    $transacoes = 0;
    $esperadoTransacoes = 0;

    foreach ($manifest as $entrada) {
        $esperadoTransacoes += (int) $entrada['transacoes'];
        $extrato = $leitor->lerArquivo($pastaFixtures.'/'.$entrada['arquivo']);

        expect($extrato->dataGeracao)->not->toBeNull();

        foreach ($extrato->transacoes as $_) {
            $transacoes++;
        }
    }

    expect(count($manifest))->toBeGreaterThan(40)
        ->and($transacoes)->toBe($esperadoTransacoes);
})->group('amostra');

test('amostra bruta em exemplos/ (opcional, só local)', function () use ($pastaExemplos) {
    $leitor = new LeitorOfx(new DateTimeZone('America/Sao_Paulo'));
    $hashes = [];
    $transacoes = 0;
    $rejeitados = 0;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pastaExemplos));
    foreach ($iterator as $arquivo) {
        if (! $arquivo->isFile()) {
            continue;
        }

        $bytes = file_get_contents($arquivo->getPathname());
        if ($bytes === false || stripos($bytes, '<OFX') === false) {
            continue;
        }

        $hash = hash('sha256', $bytes);
        if (isset($hashes[$hash])) {
            continue;
        }
        $hashes[$hash] = true;

        try {
            $extrato = $leitor->lerArquivo($arquivo->getPathname());
        } catch (OfxInvalido) {
            $rejeitados++;

            continue;
        }

        expect($extrato->dataGeracao)->not->toBeNull();
        foreach ($extrato->transacoes as $_) {
            $transacoes++;
        }
    }

    expect($rejeitados)->toBe(0)
        ->and($transacoes)->toBeGreaterThan(400_000);
})->skip(! is_dir($pastaExemplos), 'Pasta exemplos/ ausente.')
    ->group('exemplos-local');
