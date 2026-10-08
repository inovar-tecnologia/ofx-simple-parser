<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

use InovarTecnologia\OfxSimpleParser\Estrategias\DatasBradesco;
use InovarTecnologia\OfxSimpleParser\Estrategias\DatasSantander;

final class ProcessadorExtrato
{
    /** @param list<Estrategia> $estrategias */
    public function __construct(
        private array $estrategias,
    ) {}

    public static function padrao(): self
    {
        return new self([
            new DatasSantander,
            new DatasBradesco,
        ]);
    }

    public function processar(Extrato $extrato, OfxBruto $bruto): Extrato
    {
        foreach ($this->estrategias as $estrategia) {
            if ($estrategia->aceita($extrato, $bruto)) {
                $extrato = $estrategia->processa($extrato, $bruto);
            }
        }

        return $extrato;
    }
}
