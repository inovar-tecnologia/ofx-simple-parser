<?php

declare(strict_types=1);

namespace InovarTecnologia\OfxSimpleParser\Interno;

/** Tags OFX interpretadas por este pacote. */
enum TagOfx: string
{
    case OFX = 'OFX';
    case STMTRS = 'STMTRS';
    case CCSTMTRS = 'CCSTMTRS';
    case STMTTRN = 'STMTTRN';

    case BANKID = 'BANKID';
    case ACCTID = 'ACCTID';
    case DTSTART = 'DTSTART';
    case DTEND = 'DTEND';
    case DTSERVER = 'DTSERVER';

    case TRNTYPE = 'TRNTYPE';
    case DTPOSTED = 'DTPOSTED';
    case TRNAMT = 'TRNAMT';
    case NAME = 'NAME';
    case MEMO = 'MEMO';

    public function eh(string $tag): bool
    {
        return $this->value === $tag;
    }

    /** @return list<self> */
    public static function cabecalhoObrigatorias(): array
    {
        return [
            self::BANKID,
            self::ACCTID,
            self::DTSTART,
            self::DTEND,
            self::DTSERVER,
        ];
    }

    /** @return list<self> */
    public static function transacaoObrigatorias(): array
    {
        return [
            self::TRNTYPE,
            self::DTPOSTED,
            self::TRNAMT,
        ];
    }
}
