<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

use InovarTecnologia\OfxSimpleParser\Interno\OrigemOfx;
use InovarTecnologia\OfxSimpleParser\Interno\TextoOfx;

final readonly class OfxBruto
{
    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public \DateTimeZone $fuso,
        public array $tags,
        private OrigemOfx $origem,
    ) {}

    /**
     * @return \Generator<int, array<string, string>, mixed, void>
     */
    public function transacoes(): \Generator
    {
        foreach ($this->origem->transacoesBrutas() as $tags) {
            $normalizadas = [];
            foreach ($tags as $tag => $valorBruto) {
                $normalizadas[$tag] = TextoOfx::normalizar(TextoOfx::paraUtf8($valorBruto));
            }

            yield $normalizadas;
        }
    }
}
