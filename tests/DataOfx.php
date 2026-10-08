<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\DataOfx;
use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

mutates(...alvosMutacao());

test('paraExtrato aceita vazio como null', function () {
    $fuso = new DateTimeZone('UTC');

    expect(DataOfx::paraExtrato(null, $fuso, TagOfx::DTSERVER))->toBeNull()
        ->and(DataOfx::paraExtrato('   ', $fuso, TagOfx::DTSERVER))->toBeNull();
});

test('paraExtrato ignora data zerada', function () {
    $fuso = new DateTimeZone('UTC');

    expect(DataOfx::paraExtrato('00000000', $fuso, TagOfx::DTSERVER))->toBeNull();
});

test('paraTransacao aceita ISO 8601', function () {
    $fuso = new DateTimeZone('America/Sao_Paulo');
    $data = DataOfx::paraTransacao('2023-09-28T10:00:00-03:00', $fuso, TagOfx::DTPOSTED);

    expect($data->format('Y-m-d H:i:s'))->toBe('2023-09-28 10:00:00');
});

test('paraTransacao rejeita data zerada', function () {
    $fuso = new DateTimeZone('UTC');

    DataOfx::paraTransacao('00000000', $fuso, TagOfx::DTPOSTED);
})->throws(OfxInvalido::class);

test('paraTransacao rejeita formato invalido', function () {
    $fuso = new DateTimeZone('UTC');

    DataOfx::paraTransacao('nao-e-data', $fuso, TagOfx::DTPOSTED);
})->throws(OfxInvalido::class);

test('diaDeDtPosted rejeita valor sem data reconhecivel', function () {
    DataOfx::diaDeDtPosted('???');
})->throws(OfxInvalido::class);

test('diaDeDtPosted usa primeiros oito digitos', function () {
    expect(DataOfx::diaDeDtPosted('20230928103000[-03:EST]'))->toBe('2023-09-28');
});
