<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Estrategias;

use InovarTecnologia\OfxSimpleParser\Estrategia;
use InovarTecnologia\OfxSimpleParser\Extrato;
use InovarTecnologia\OfxSimpleParser\Interno\DataOfx;
use InovarTecnologia\OfxSimpleParser\Interno\DatasMalformadas;
use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;
use InovarTecnologia\OfxSimpleParser\OfxBruto;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

final class DatasSantander implements Estrategia
{
    public function aceita(Extrato $extrato, OfxBruto $bruto): bool
    {
        if ($extrato->codigoBanco !== 33) {
            return false;
        }

        return $extrato->dataGeracao === null
            || $extrato->dataInicio === null
            || $extrato->dataFim === null;
    }

    public function processa(Extrato $extrato, OfxBruto $bruto): Extrato
    {
        $ultimaTransacao = $this->ultimaTransacao($bruto);

        return $extrato->com(
            dataGeracao: $extrato->dataGeracao ?? $this->resolver($bruto->tags[TagOfx::DTSERVER->value] ?? null, $bruto, $ultimaTransacao),
            dataInicio: $extrato->dataInicio ?? $this->resolver($bruto->tags[TagOfx::DTSTART->value] ?? null, $bruto, $ultimaTransacao),
            dataFim: $extrato->dataFim ?? $this->resolver($bruto->tags[TagOfx::DTEND->value] ?? null, $bruto, $ultimaTransacao),
        );
    }

    private function ultimaTransacao(OfxBruto $bruto): ?\DateTimeImmutable
    {
        $ultima = null;
        foreach ($bruto->transacoes() as $tags) {
            if (! isset($tags[TagOfx::DTPOSTED->value])) {
                throw OfxInvalido::motivo('Transação sem DTPOSTED.', TagOfx::DTPOSTED);
            }

            $ultima = DataOfx::paraTransacao($tags[TagOfx::DTPOSTED->value], $bruto->fuso, TagOfx::DTPOSTED);
        }

        return $ultima;
    }

    private function resolver(?string $valor, OfxBruto $bruto, ?\DateTimeImmutable $ultimaTransacao): ?\DateTimeImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $digitos = DatasMalformadas::digitos($valor);
        $comprimento = strlen($digitos);
        if ($comprimento === 8 || $comprimento === 14) {
            return DataOfx::paraExtrato($valor, $bruto->fuso, TagOfx::DTSERVER);
        }

        if ($comprimento < 10 || $comprimento > 13) {
            return null;
        }

        $leituras = DatasMalformadas::leituras($digitos);
        if ($leituras === []) {
            return null;
        }

        $dias = array_values(array_unique(array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $leituras)));

        if (count($dias) > 1 && $ultimaTransacao !== null) {
            $limite = $ultimaTransacao->format('Y-m-d');
            $dias = array_values(array_filter($dias, static fn (string $dia): bool => $dia >= $limite));
        }

        if ($dias === []) {
            return null;
        }

        sort($dias);
        $diaEscolhido = $dias[0];

        $instantesDoDia = array_values(array_filter(
            $leituras,
            static fn (\DateTimeImmutable $d): bool => $d->format('Y-m-d') === $diaEscolhido,
        ));

        $escolhido = count($instantesDoDia) === 1
            ? $instantesDoDia[0]
            : \DateTimeImmutable::createFromFormat('!Y-m-d', $diaEscolhido, $bruto->fuso);

        if ($escolhido === false) {
            return null;
        }

        $sufixo = DatasMalformadas::sufixoFuso($valor);
        if ($sufixo !== '') {
            $texto = str_replace('-', '', $diaEscolhido).'000000'.$sufixo;

            return DataOfx::paraExtrato($texto, $bruto->fuso, TagOfx::DTSERVER);
        }

        return $escolhido;
    }
}
