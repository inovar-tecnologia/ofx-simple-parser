<?php

declare(strict_types=1);

use InovarTecnologia\OfxSimpleParser\LeitorOfx;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;
use InovarTecnologia\OfxSimpleParser\ProcessadorExtrato;

mutates(...alvosMutacao());

test('valor com ponto decimal', function () {
    $extrato = lerOfxMinimo(ofxMinimo(trnamt: '-3408.06'));

    expect(primeiraTransacao($extrato)->valor)->toBe(-340806);
});

test('valor com virgula decimal', function () {
    $extrato = lerOfxMinimo(ofxMinimo(trnamt: '3337,64'));

    expect(primeiraTransacao($extrato)->valor)->toBe(333764);
});

test('entidade HTML no memo', function () {
    $extrato = lerOfxMinimo(ofxMinimo(trnamt: '10.00', memo: 'PAGTO A&amp;B LTDA'));

    expect(primeiraTransacao($extrato)->memo)->toBe('PAGTO A&B LTDA');
});

test('rejeita conteudo invalido', function () {
    (new LeitorOfx)->ler('nao e ofx');
})->throws(OfxInvalido::class);

test('transacao invalida durante iteracao', function () {
    $extrato = lerOfxMinimo(ofxMinimo(trnamt: '1.234,56', extraTransacao: ''));

    iterator_to_array($extrato->transacoes);
})->throws(OfxInvalido::class);

test('duas iteracoes sobre as mesmas transacoes', function () {
    $extrato = lerOfxMinimo(ofxMinimo(trnamt: '10.00', extraTransacao: blocoTransacaoOfx('20.00')));

    $a = iterator_to_array($extrato->transacoes);
    $b = iterator_to_array($extrato->transacoes);

    expect($a)->toHaveCount(2)
        ->and(array_column($a, 'fingerprint'))->toBe(array_column($b, 'fingerprint'));
});

test('Bradesco data geracao crua e normalizada', function () {
    $conteudo = file_get_contents(__DIR__.'/Fixtures/ofx/bradesco-01.ofx');
    $cru = (new LeitorOfx(processador: new ProcessadorExtrato([])))->ler($conteudo);

    expect($cru->dataGeracao)->toBeNull();

    $normal = (new LeitorOfx)->ler($conteudo);

    expect($normal->dataGeracao)->not->toBeNull();
});

test('lerArquivo equivale a ler conteudo', function () {
    $caminho = __DIR__.'/Fixtures/ofx/itau-01.ofx';
    $a = (new LeitorOfx)->lerArquivo($caminho);
    $b = (new LeitorOfx)->ler(file_get_contents($caminho));

    expect($a->codigoBanco)->toBe($b->codigoBanco)
        ->and(iterator_to_array($a->transacoes)[0]->fingerprint)
        ->toBe(iterator_to_array($b->transacoes)[0]->fingerprint);
});
