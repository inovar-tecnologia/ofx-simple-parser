<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

final class Transacoes implements \IteratorAggregate
{
    /** @param \Closure(): \Generator<int, Transacao, mixed, void> $gerador */
    public function __construct(
        private \Closure $gerador,
    ) {}

    public function getIterator(): \Generator
    {
        $gerador = ($this->gerador)();

        yield from $gerador;
    }
}
