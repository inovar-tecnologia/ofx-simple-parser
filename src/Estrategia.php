<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

interface Estrategia
{
    public function aceita(Extrato $extrato, OfxBruto $bruto): bool;

    public function processa(Extrato $extrato, OfxBruto $bruto): Extrato;
}
