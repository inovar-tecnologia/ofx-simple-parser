<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\Interno\DatasMalformadas;

mutates(...alvosMutacao());

test('leituras vazias para digitos invalidos', function () {
    expect(DatasMalformadas::leituras('abc'))->toBe([])
        ->and(DatasMalformadas::leituras('2024'))->toBe([])
        ->and(DatasMalformadas::leituras('202413991200'))->toBe([]);
});

test('leituras validas para data malformada', function () {
    $leituras = DatasMalformadas::leituras('2024051315034');

    expect($leituras)->not->toBeEmpty()
        ->and($leituras[0]->format('Y-m-d'))->toBe('2024-05-13');
});

test('digitos e sufixo de fuso', function () {
    expect(DatasMalformadas::digitos('  20240513[-3:GMT]'))->toBe('20240513')
        ->and(DatasMalformadas::sufixoFuso('2024051315034[-3:GMT]'))->toBe('[-3:GMT]')
        ->and(DatasMalformadas::sufixoFuso('20240513'))->toBe('');
});
