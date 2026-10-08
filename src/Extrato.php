<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

final readonly class Extrato
{
    public function __construct(
        public int $codigoBanco,
        public string $numeroConta,
        public ?\DateTimeImmutable $dataInicio,
        public ?\DateTimeImmutable $dataFim,
        public ?\DateTimeImmutable $dataGeracao,
        public Transacoes $transacoes,
    ) {}

    public function com(
        int|null|false $codigoBanco = false,
        string|null|false $numeroConta = false,
        \DateTimeImmutable|null|false $dataInicio = false,
        \DateTimeImmutable|null|false $dataFim = false,
        \DateTimeImmutable|null|false $dataGeracao = false,
        Transacoes|null|false $transacoes = false,
    ): self {
        return new self(
            codigoBanco: $codigoBanco === false ? $this->codigoBanco : $codigoBanco,
            numeroConta: $numeroConta === false ? $this->numeroConta : $numeroConta,
            dataInicio: $dataInicio === false ? $this->dataInicio : $dataInicio,
            dataFim: $dataFim === false ? $this->dataFim : $dataFim,
            dataGeracao: $dataGeracao === false ? $this->dataGeracao : $dataGeracao,
            transacoes: $transacoes === false ? $this->transacoes : $transacoes,
        );
    }
}
