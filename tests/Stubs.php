<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;
use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\ProcessadorExtrato;

mutates(...alvosMutacao());

dataset('manifesto', fn (): iterable => manifestoStubs());

test('stub sem estrategias', function (array $entrada) {
    $conteudo = file_get_contents(__DIR__.'/Fixtures/ofx/'.$entrada['arquivo']);
    $extrato = (new LeitorOfx(new DateTimeZone('UTC'), new ProcessadorExtrato([])))->ler($conteudo);

    expect($extrato->codigoBanco)->toBe((int) $entrada['codigoBanco'])
        ->and($extrato->numeroConta)->toBe($entrada['numeroConta']);

    $n = 0;
    $soma = 0;
    foreach ($extrato->transacoes as $transacao) {
        $n++;
        $soma += $transacao->valor;
    }

    expect($n)->toBe((int) $entrada['transacoes'])
        ->and($soma)->toBe((int) $entrada['somaValoresCentavos']);

    foreach ([[TagOfx::DTSERVER, 'dataGeracao'], [TagOfx::DTSTART, 'dataInicio'], [TagOfx::DTEND, 'dataFim']] as [$tag, $campo]) {
        $deveSerNula = in_array($tag->value, $entrada['datasNulas'] ?? [], true);
        expect($extrato->{$campo} === null)->toBe($deveSerNula, "{$tag->value} em {$entrada['arquivo']}");
    }
})->with('manifesto');

test('stub com estrategias padrao', function (array $entrada) {
    $conteudo = file_get_contents(__DIR__.'/Fixtures/ofx/'.$entrada['arquivo']);
    $extrato = (new LeitorOfx(new DateTimeZone('UTC')))->ler($conteudo);

    expect($extrato->dataGeracao)->not->toBeNull()
        ->and($extrato->dataInicio)->not->toBeNull()
        ->and($extrato->dataFim)->not->toBeNull();
})->with('manifesto');
