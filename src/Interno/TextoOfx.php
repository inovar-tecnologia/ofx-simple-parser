<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

/** @internal */
final class TextoOfx
{
    public static function removerBom(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return substr($bytes, 3);
        }

        return $bytes;
    }

    public static function paraUtf8(string $valorBruto): string
    {
        if ($valorBruto === '') {
            return '';
        }

        if (preg_match('//u', $valorBruto) === 1) {
            return $valorBruto;
        }

        return self::windows1252ParaUtf8($valorBruto);
    }

    public static function normalizar(string $texto): string
    {
        $texto = trim($texto);

        return self::decodificarEntidades($texto);
    }

    private static function decodificarEntidades(string $texto): string
    {
        return preg_replace_callback(
            '/&(?:#x([0-9A-Fa-f]+)|#(\d+)|amp|lt|gt|quot|apos);/',
            static function (array $partes): string {
                if (isset($partes[1]) && $partes[1] !== '') {
                    $codigo = (int) hexdec($partes[1]);
                } elseif (isset($partes[2]) && $partes[2] !== '') {
                    $codigo = (int) $partes[2];
                } else {
                    return match ($partes[0]) {
                        '&amp;' => '&',
                        '&lt;' => '<',
                        '&gt;' => '>',
                        '&quot;' => '"',
                        '&apos;' => "'",
                        default => $partes[0],
                    };
                }

                if ($codigo < 0 || $codigo > 0x10FFFF) {
                    return $partes[0];
                }

                return html_entity_decode('&#'.$codigo.';', ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8');
            },
            $texto,
        ) ?? $texto;
    }

    private static function windows1252ParaUtf8(string $bytes): string
    {
        $mapa = [
            "\x80" => "\xE2\x82\xAC", "\x82" => "\xE2\x80\x9A", "\x83" => "\xC6\x92", "\x84" => "\xE2\x80\x9E",
            "\x85" => "\xE2\x80\xA6", "\x86" => "\xE2\x80\xA0", "\x87" => "\xE2\x80\xA1", "\x88" => "\xCB\x86",
            "\x89" => "\xE2\x80\xB0", "\x8A" => "\xC5\xA0", "\x8B" => "\xE2\x80\xB9", "\x8C" => "\xC5\x92",
            "\x8E" => "\xC5\xBD", "\x91" => "\xE2\x80\x98", "\x92" => "\xE2\x80\x99", "\x93" => "\xE2\x80\x9C",
            "\x94" => "\xE2\x80\x9D", "\x95" => "\xE2\x80\xA2", "\x96" => "\xE2\x80\x93", "\x97" => "\xE2\x80\x94",
            "\x98" => "\xCB\x9C", "\x99" => "\xE2\x84\xA2", "\x9A" => "\xC5\xA1", "\x9B" => "\xE2\x80\xBA",
            "\x9C" => "\xC5\x93", "\x9E" => "\xC5\xBE", "\x9F" => "\xC5\xB8",
        ];

        $resultado = '';
        $comprimento = strlen($bytes);
        for ($i = 0; $i < $comprimento; $i++) {
            $byte = $bytes[$i];
            if (isset($mapa[$byte])) {
                $resultado .= $mapa[$byte];
            } elseif (ord($byte) >= 0xA0) {
                $codigo = ord($byte);
                $resultado .= chr(0xC0 | ($codigo >> 6)).chr(0x80 | ($codigo & 0x3F));
            } else {
                $resultado .= $byte;
            }
        }

        return $resultado;
    }
}
