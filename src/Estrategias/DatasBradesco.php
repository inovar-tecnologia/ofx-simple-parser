<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Estrategias;

use InovarTecnologia\OfxSimpleParser\Estrategia;
use InovarTecnologia\OfxSimpleParser\Extrato;
use InovarTecnologia\OfxSimpleParser\Interno\DataOfx;
use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;
use InovarTecnologia\OfxSimpleParser\OfxBruto;
use InovarTecnologia\OfxSimpleParser\OfxInvalido;

final class DatasBradesco implements Estrategia
{
    public function aceita(Extrato $extrato, OfxBruto $bruto): bool
    {
        return $extrato->codigoBanco === 237 && $extrato->dataGeracao === null;
    }

    public function processa(Extrato $extrato, OfxBruto $bruto): Extrato
    {
        $dataGeracao = $extrato->dataFim;
        if ($dataGeracao === null) {
            return $extrato;
        }

        $primeira = null;
        $ultima = null;

        foreach ($bruto->transacoes() as $tags) {
            if (! isset($tags[TagOfx::DTPOSTED->value])) {
                throw OfxInvalido::motivo('Transação sem DTPOSTED.', TagOfx::DTPOSTED);
            }

            $data = DataOfx::paraTransacao($tags[TagOfx::DTPOSTED->value], $bruto->fuso, TagOfx::DTPOSTED);
            $primeira = $primeira === null || $data < $primeira ? $data : $primeira;
            $ultima = $ultima === null || $data > $ultima ? $data : $ultima;
        }

        return $extrato->com(
            dataGeracao: $dataGeracao,
            dataInicio: $primeira ?? $extrato->dataInicio,
            dataFim: $ultima ?? $extrato->dataFim,
        );
    }
}
