<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

/** @internal */
final class DatasMalformadas
{
    /**
     * @return list<\DateTimeImmutable>
     */
    public static function leituras(string $digitos): array
    {
        if (! preg_match('/^(\d{4})(\d+)$/', $digitos, $partes)) {
            return [];
        }

        $ano = (int) $partes[1];
        $resto = $partes[2];
        $resultado = [];

        $rec = static function (string $s, array $componentes) use (&$rec, $ano, &$resultado): void {
            if (count($componentes) === 5) {
                if ($s === '') {
                    [$mes, $dia, $hora, $minuto, $segundo] = $componentes;
                    if (! checkdate($mes, $dia, $ano)) {
                        return;
                    }

                    if ($hora > 23 || $minuto > 59 || $segundo > 59) {
                        return;
                    }

                    $data = \DateTimeImmutable::createFromFormat(
                        '!Y-m-d H:i:s',
                        sprintf(
                            '%04d-%02d-%02d %02d:%02d:%02d',
                            $ano,
                            $mes,
                            $dia,
                            $hora,
                            $minuto,
                            $segundo,
                        ),
                    );
                    if ($data !== false) {
                        $resultado[] = $data;
                    }
                }

                return;
            }

            foreach ([1, 2] as $tamanho) {
                if (strlen($s) >= $tamanho) {
                    $rec(substr($s, $tamanho), [...$componentes, (int) substr($s, 0, $tamanho)]);
                }
            }
        };

        $rec($resto, []);

        usort($resultado, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $resultado;
    }

    public static function sufixoFuso(string $valor): string
    {
        if (preg_match('/(\[[+-]?\d+(?:\.\d+)?:[^\]]*\])$/', $valor, $partes)) {
            return $partes[1];
        }

        return '';
    }

    public static function digitos(string $valor): string
    {
        if (preg_match('/^(\d+)/', trim($valor), $partes)) {
            return $partes[1];
        }

        return '';
    }
}
