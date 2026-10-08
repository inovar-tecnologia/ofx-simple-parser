<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

/** @internal */
final class FingerprintOfx
{
    public static function gerar(int $codigoBanco, string $numeroConta, string $dia, int $valor, int $ordinal): string
    {
        $texto = $codigoBanco.'|'.trim($numeroConta).'|'.$dia.'|'.$valor.'|'.$ordinal;

        return hash('sha256', $texto);
    }
}
