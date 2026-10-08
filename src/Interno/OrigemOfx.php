<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

use InovarTecnologia\OfxSimpleParser\OfxInvalido;

/** @internal */
final class OrigemOfx
{
    /** @param resource $stream */
    public function __construct(
        private $stream,
        private readonly bool $fecharAoDestruir,
        private int $offsetPrimeiraTransacao = -1,
    ) {}

    public function __destruct()
    {
        if ($this->fecharAoDestruir && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public static function deString(string $conteudo): self
    {
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            throw OfxInvalido::motivo('Não foi possível abrir a origem em memória.');
        }
        fwrite($stream, $conteudo);
        rewind($stream);

        return new self($stream, true);
    }

    public static function deArquivo(string $caminho): self
    {
        if (! is_readable($caminho)) {
            throw OfxInvalido::motivo('Não foi possível ler o arquivo.', null, $caminho);
        }

        $stream = fopen($caminho, 'rb');
        if ($stream === false) {
            throw OfxInvalido::motivo('Não foi possível abrir o arquivo.', null, $caminho);
        }

        return new self($stream, true);
    }

    /** @param resource $stream */
    public static function deStream($stream): self
    {
        if (! is_resource($stream)) {
            throw OfxInvalido::motivo('Stream inválido.');
        }

        $meta = stream_get_meta_data($stream);
        if ($meta['seekable']) {
            return new self($stream, false);
        }

        $copia = fopen('php://temp', 'r+b');
        if ($copia === false) {
            throw OfxInvalido::motivo('Não foi possível copiar o stream.');
        }

        stream_copy_to_stream($stream, $copia);
        rewind($copia);

        return new self($copia, true);
    }

    public function offsetPrimeiraTransacao(): int
    {
        return $this->offsetPrimeiraTransacao;
    }

    public function definirOffsetPrimeiraTransacao(int $offset): void
    {
        $this->offsetPrimeiraTransacao = $offset;
    }

    /** @return \Generator<int, TokenOfx, mixed, void> */
    public function tokens(int $offset = 0): \Generator
    {
        if (! is_resource($this->stream)) {
            return;
        }

        if (fseek($this->stream, $offset) !== 0) {
            throw OfxInvalido::motivo('Não foi possível posicionar a origem.');
        }

        while (! feof($this->stream)) {
            $caractere = fgetc($this->stream);
            if ($caractere === false) {
                break;
            }

            if ($caractere !== '<') {
                continue;
            }

            $offset = ftell($this->stream) - 1;
            $nomeTag = '';
            while (($caractere = fgetc($this->stream)) !== false) {
                if ($caractere === '>') {
                    break;
                }

                $nomeTag .= $caractere;
            }

            if ($caractere === false || ! preg_match('/^(\/)?([A-Za-z0-9]+)$/', $nomeTag, $partes)) {
                continue;
            }

            $valorBruto = stream_get_line($this->stream, 0, "\n");
            if ($valorBruto === false) {
                $valorBruto = '';
            }

            if ($valorBruto !== '' && str_contains($valorBruto, '<')) {
                $posicao = strpos($valorBruto, '<');
                $sobra = substr($valorBruto, $posicao);
                $valorBruto = substr($valorBruto, 0, $posicao);
                $retrocesso = strlen($sobra);
                fseek($this->stream, -$retrocesso, SEEK_CUR);
            }

            yield new TokenOfx(
                fechamento: $partes[1] === '/',
                tag: $partes[2],
                valorBruto: $valorBruto,
                offset: $offset,
            );
        }
    }

    /**
     * @return \Generator<int, array<string, string>, mixed, void>
     */
    public function transacoesBrutas(): \Generator
    {
        if ($this->offsetPrimeiraTransacao < 0) {
            return;
        }

        $atual = [];
        $dentro = false;

        foreach ($this->tokens($this->offsetPrimeiraTransacao) as $token) {
            if (TagOfx::STMTRS->eh($token->tag) && ! $token->fechamento) {
                throw OfxInvalido::motivo('Mais de um extrato de conta corrente no arquivo.', TagOfx::STMTRS);
            }

            if (TagOfx::CCSTMTRS->eh($token->tag) && ! $token->fechamento) {
                throw OfxInvalido::motivo('Extrato de cartão de crédito não suportado.', TagOfx::CCSTMTRS);
            }

            if (TagOfx::STMTTRN->eh($token->tag)) {
                if ($token->fechamento) {
                    if ($dentro) {
                        yield $atual;
                        $atual = [];
                        $dentro = false;
                    }

                    continue;
                }

                if ($dentro && $atual !== []) {
                    yield $atual;
                }

                $atual = [];
                $dentro = true;

                continue;
            }

            if ($dentro && ! $token->fechamento) {
                $atual[$token->tag] = $token->valorBruto;
            }
        }

        if ($dentro && $atual !== []) {
            yield $atual;
        }
    }
}

