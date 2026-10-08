<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

use InovarTecnologia\OfxSimpleParser\OfxInvalido;

/** @internal */
final class DataOfx
{
    public static function paraExtrato(?string $valor, \DateTimeZone $fuso, TagOfx|string $tag): ?\DateTimeImmutable
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        try {
            return self::converter(trim($valor), $fuso, $tag, estrito: false);
        } catch (OfxInvalido) {
            return null;
        }
    }

    public static function paraTransacao(string $valor, \DateTimeZone $fuso, TagOfx|string $tag): \DateTimeImmutable
    {
        return self::converter(trim($valor), $fuso, $tag, estrito: true);
    }

    private static function converter(string $valor, \DateTimeZone $fuso, TagOfx|string $tag, bool $estrito): \DateTimeImmutable
    {
        if (self::ehZerada($valor)) {
            if ($estrito) {
                throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
            }

            throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $valor)) {
            try {
                return new \DateTimeImmutable($valor)->setTimezone($fuso);
            } catch (\Exception) {
                throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
            }
        }

        $fusoInformado = null;
        $deslocamento = null;
        if (preg_match('/^(.+?)\[([+-]?\d+(?:\.\d+)?):[^\]]*\]$/', $valor, $partes)) {
            $valor = $partes[1];
            $deslocamento = (float) $partes[2];
            $fusoInformado = self::fusoDeDeslocamento($deslocamento);
        }

        $valor = rtrim($valor);

        if (preg_match('/^(\d{8})(\d{6})?(\.\d+)?$/', $valor, $partes)) {
            $formato = isset($partes[2]) ? 'YmdHis' : 'Ymd';
            $texto = $partes[1].($partes[2] ?? '');

            $data = \DateTimeImmutable::createFromFormat('!'.$formato, $texto, $fusoInformado ?? $fuso);
            if ($data === false || $data->format($formato) !== $texto) {
                if (! $estrito) {
                    throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
                }
                throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
            }

            if ($fusoInformado !== null) {
                return $data->setTimezone($fuso);
            }

            return $data;
        }

        if ($estrito) {
            throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
        }

        throw OfxInvalido::motivo('Data inválida.', $tag, $valor);
    }

    public static function diaDeDtPosted(string $valorBruto): string
    {
        $valor = trim($valorBruto);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $valor, $partes)) {
            return $partes[1];
        }

        if (preg_match('/^(\d{8})/', $valor, $partes)) {
            $data = \DateTimeImmutable::createFromFormat('!Ymd', $partes[1]);

            return $data !== false ? $data->format('Y-m-d') : $partes[1];
        }

        throw OfxInvalido::motivo('Data inválida para fingerprint.', TagOfx::DTPOSTED, $valor);
    }

    private static function ehZerada(string $valor): bool
    {
        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        return $digitos !== '' && preg_match('/^0+$/', $digitos) === 1;
    }

    private static function fusoDeDeslocamento(float $horas): \DateTimeZone
    {
        $sinal = $horas < 0 ? '-' : '+';
        $horas = abs($horas);
        $h = (int) floor($horas);
        $m = (int) round(($horas - $h) * 60);

        return new \DateTimeZone(sprintf('%s%02d:%02d', $sinal, $h, $m));
    }
}
