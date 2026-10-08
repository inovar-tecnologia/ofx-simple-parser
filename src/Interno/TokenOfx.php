<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

final readonly class TokenOfx
{
    public function __construct(
        public bool $fechamento,
        public string $tag,
        public string $valorBruto,
        public int $offset,
    ) {}
}
