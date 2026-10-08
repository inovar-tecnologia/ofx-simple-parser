<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser;

use InovarTecnologia\OfxSimpleParser\Interno\DataOfx;
use InovarTecnologia\OfxSimpleParser\Interno\FingerprintOfx;
use InovarTecnologia\OfxSimpleParser\Interno\OrigemOfx;
use InovarTecnologia\OfxSimpleParser\Interno\TagOfx;
use InovarTecnologia\OfxSimpleParser\Interno\TextoOfx;
use InovarTecnologia\OfxSimpleParser\Interno\ValorOfx;

final class LeitorOfx
{
    private readonly \DateTimeZone $fuso;

    private readonly ProcessadorExtrato $processador;

    public function __construct(
        ?\DateTimeZone $fuso = null,
        ?ProcessadorExtrato $processador = null,
    ) {
        $this->fuso = $fuso ?? new \DateTimeZone(date_default_timezone_get());
        $this->processador = $processador ?? ProcessadorExtrato::padrao();
    }

    public function ler(string $conteudo): Extrato
    {
        $conteudo = TextoOfx::removerBom($conteudo);

        return $this->lerOrigem(OrigemOfx::deString($conteudo));
    }

    public function lerArquivo(string $caminho): Extrato
    {
        return $this->lerOrigem(OrigemOfx::deArquivo($caminho));
    }

    /** @param resource $stream */
    public function lerStream($stream): Extrato
    {
        return $this->lerOrigem(OrigemOfx::deStream($stream));
    }

    private function lerOrigem(OrigemOfx $origem): Extrato
    {
        $temOfx = false;
        $temStmtrs = false;
        $temCcstmtrs = false;
        /** @var array<string, string> $tagsBrutas */
        $tagsBrutas = [];

        foreach ($origem->tokens(0) as $token) {
            if (TagOfx::OFX->eh($token->tag) && ! $token->fechamento) {
                $temOfx = true;
            }

            if (TagOfx::STMTRS->eh($token->tag) && ! $token->fechamento) {
                $temStmtrs = true;
            }

            if (TagOfx::CCSTMTRS->eh($token->tag) && ! $token->fechamento) {
                $temCcstmtrs = true;
            }

            if (TagOfx::STMTTRN->eh($token->tag) && ! $token->fechamento) {
                $origem->definirOffsetPrimeiraTransacao($token->offset);
                break;
            }

            if (! $token->fechamento) {
                $tagsBrutas[$token->tag] = TextoOfx::normalizar(TextoOfx::paraUtf8($token->valorBruto));
            }
        }

        if (! $temOfx) {
            throw OfxInvalido::motivo('Conteúdo não é um arquivo OFX.');
        }

        if (! $temStmtrs) {
            if ($temCcstmtrs) {
                throw OfxInvalido::motivo('Extrato de cartão de crédito não suportado.', TagOfx::CCSTMTRS);
            }

            throw OfxInvalido::motivo('Extrato de conta corrente não encontrado.', TagOfx::STMTRS);
        }

        foreach (TagOfx::cabecalhoObrigatorias() as $obrigatoria) {
            if (! array_key_exists($obrigatoria->value, $tagsBrutas)) {
                throw OfxInvalido::motivo('Campo obrigatório ausente.', $obrigatoria);
            }
        }

        $codigoBanco = (int) $tagsBrutas[TagOfx::BANKID->value];

        $extrato = new Extrato(
            codigoBanco: $codigoBanco,
            numeroConta: $tagsBrutas[TagOfx::ACCTID->value],
            dataInicio: DataOfx::paraExtrato($tagsBrutas[TagOfx::DTSTART->value], $this->fuso, TagOfx::DTSTART),
            dataFim: DataOfx::paraExtrato($tagsBrutas[TagOfx::DTEND->value], $this->fuso, TagOfx::DTEND),
            dataGeracao: DataOfx::paraExtrato($tagsBrutas[TagOfx::DTSERVER->value], $this->fuso, TagOfx::DTSERVER),
            transacoes: $this->transacoes($origem, $codigoBanco, $tagsBrutas[TagOfx::ACCTID->value]),
        );

        $bruto = new OfxBruto($this->fuso, $tagsBrutas, $origem);

        return $this->processador->processar($extrato, $bruto);
    }

    private function transacoes(OrigemOfx $origem, int $codigoBanco, string $numeroConta): Transacoes
    {
        $fuso = $this->fuso;

        return new Transacoes(static function () use ($origem, $codigoBanco, $numeroConta, $fuso): \Generator {
            /** @var array<string, int> $ordinais */
            $ordinais = [];

            foreach ($origem->transacoesBrutas() as $tagsBrutas) {
                foreach (TagOfx::transacaoObrigatorias() as $obrigatoria) {
                    if (! isset($tagsBrutas[$obrigatoria->value]) || trim($tagsBrutas[$obrigatoria->value]) === '') {
                        throw OfxInvalido::motivo('Campo obrigatório ausente na transação.', $obrigatoria);
                    }
                }

                $tipo = TextoOfx::normalizar(TextoOfx::paraUtf8($tagsBrutas[TagOfx::TRNTYPE->value]));
                $valorBruto = TextoOfx::paraUtf8($tagsBrutas[TagOfx::TRNAMT->value]);
                $valor = ValorOfx::paraCentavos($valorBruto, TagOfx::TRNAMT);

                $dtBruto = TextoOfx::paraUtf8($tagsBrutas[TagOfx::DTPOSTED->value]);
                $data = DataOfx::paraTransacao($dtBruto, $fuso, TagOfx::DTPOSTED);
                $dia = DataOfx::diaDeDtPosted($dtBruto);

                $chave = $dia.'|'.$valor;
                $ordinais[$chave] = ($ordinais[$chave] ?? 0) + 1;

                $nome = null;
                if (isset($tagsBrutas[TagOfx::NAME->value])) {
                    $nomeTexto = TextoOfx::normalizar(TextoOfx::paraUtf8($tagsBrutas[TagOfx::NAME->value]));
                    $nome = $nomeTexto === '' ? null : $nomeTexto;
                }

                $memo = isset($tagsBrutas[TagOfx::MEMO->value])
                    ? TextoOfx::normalizar(TextoOfx::paraUtf8($tagsBrutas[TagOfx::MEMO->value]))
                    : '';

                yield new Transacao(
                    fingerprint: FingerprintOfx::gerar($codigoBanco, $numeroConta, $dia, $valor, $ordinais[$chave]),
                    tipo: $tipo,
                    valor: $valor,
                    data: $data,
                    nome: $nome,
                    memo: $memo,
                );
            }
        });
    }
}
