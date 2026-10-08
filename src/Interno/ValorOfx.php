<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

use InovarTecnologia\OfxSimpleParser\OfxInvalido;

/** @internal */
final class ValorOfx
{
    public static function paraCentavos(string $valor, TagOfx|string $tag): int
    {
        $valor = trim($valor);
        if (! preg_match('/^([+-]?)(\d+)(?:[.,](\d{1,2}))?$/', $valor, $partes)) {
            throw OfxInvalido::motivo('Valor inválido.', $tag, $valor);
        }

        $sinal = $partes[1] === '-' ? -1 : 1;
        $inteiro = (int) $partes[2];
        $decimais = $partes[3] ?? '';
        if ($decimais !== '' && strlen($decimais) === 1) {
            $decimais .= '0';
        }

        $centavos = $inteiro * 100 + (int) ($decimais === '' ? 0 : $decimais);

        return $sinal * $centavos;
    }
}
