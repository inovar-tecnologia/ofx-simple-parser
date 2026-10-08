<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;

final class OfxInvalido extends \RuntimeException
{
    public static function motivo(string $motivo, TagOfx|string|null $tag = null, ?string $valor = null): self
    {
        $mensagem = $motivo;
        if ($tag !== null) {
            $nomeTag = $tag instanceof TagOfx ? $tag->value : $tag;
            $mensagem .= ' (tag '.$nomeTag;
            if ($valor !== null) {
                $mensagem .= ', valor '.$valor;
            }
            $mensagem .= ')';
        }

        return new self($mensagem);
    }
}
