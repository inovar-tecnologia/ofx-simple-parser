<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

final readonly class Transacao
{
    public function __construct(
        public string $fingerprint,
        public string $tipo,
        public int $valor,
        public \DateTimeImmutable $data,
        public ?string $nome,
        public string $memo,
    ) {}
}
