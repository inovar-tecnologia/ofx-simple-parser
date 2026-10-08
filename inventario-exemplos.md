# Inventário de exemplos OFX

Gerado em 2026-10-07 23:29 — 9883 arquivos em `exemplos/` (8964 únicos, 919 duplicados por conteúdo).

Regenerar com `python3 scripts/inventario.py`. Detalhe por arquivo em `inventario-exemplos.csv`.
Duplicados por conteúdo ficam fora das contagens abaixo.

## Perfis encontrados

| Banco | Tipo | Formato | Versão | Codificação | Quebra de linha | Tags fechadas | Fuso | BALAMT | Arquivos |
|---|---|---|---|---|---|---|---|---|---|
| Itaú | conta corrente | SGML | 102 | ASCII | CRLF | não | [-99:EST] | inteiro | 2478 |
| Itaú | conta corrente | SGML | 102 | ASCII | LF | não | [-99:EST] | decimal | 1606 |
| Bradesco | conta corrente | SGML | 102 | ASCII | LF | não | sem fuso | decimal | 1225 |
| Sicoob | conta corrente | SGML | 102 | Win-1252 | LF | sim | [-9:BRT] | decimal | 701 |
| Sicoob | conta corrente | SGML | 102 | Win-1252 | CRLF+LF | sim | [-9:BRT] | decimal | 573 |
| Sicoob | conta corrente | SGML | 102 | Win-1252 | CRLF | sim | [-9:BRT] | decimal | 542 |
| Itaú | conta corrente | SGML | 102 | ASCII | CRLF+LF | não | [-99:EST] | decimal | 330 |
| Sicoob | conta corrente | SGML | 102 | ASCII | LF | sim | [-9:BRT] | decimal | 250 |
| Santander | conta corrente | SGML | 102 | ASCII | LF | não | [-9:GMT] | decimal | 242 |
| Inter | conta corrente | SGML | 102 | ASCII | CRLF | sim | sem fuso | decimal | 211 |
| Itaú | conta corrente | SGML | 102 | ASCII | CRLF | não | [-99:EST] | decimal | 211 |
| Sicredi | conta corrente | SGML | 102 | ASCII | CRLF+LF | sim | [-9:GMT] | decimal | 145 |
| Sicredi | conta corrente | SGML | 102 | UTF-8 | CRLF+LF | sim | [-9:GMT] | decimal | 94 |
| Sicoob | conta corrente | SGML | 102 | ASCII | CRLF+LF | sim | [-9:BRT] | decimal | 65 |
| Itaú | conta corrente | SGML | 102 | UTF-8 | LF | não | [-99:EST] | decimal | 59 |
| Santander | conta corrente | SGML | 102 | ASCII | CRLF | não | [-9:GMT] | decimal | 47 |
| C6 | conta corrente | SGML | 102 | ASCII | LF | sim | [-9:BRT] | - | 41 |
| C6 | conta corrente | SGML | 102 | UTF-8 | LF | sim | [-9:BRT] | - | 40 |
| Bradesco | conta corrente | SGML | 102 | Win-1252 | LF | não | sem fuso | decimal | 21 |
| Sicredi | conta corrente | SGML | 102 | ASCII | CRLF+LF | sim | [-9:GMT] | inteiro | 20 |
| BTG | conta corrente | SGML | 102 | ASCII | LF | não | [-9:GMT] | decimal | 15 |
| Itaú | conta corrente | SGML | 102 | UTF-8 | CRLF | não | [-99:EST] | inteiro | 11 |
| Sicoob | conta corrente | SGML | 102 | ASCII | CRLF | sim | [-9:BRT] | decimal | 11 |
| Banco do Brasil | conta corrente | SGML | 102 | Win-1252 | CRLF | não | sem fuso | decimal | 8 |
| Bradesco | conta corrente | SGML | 102 | ASCII | LF | não | - | decimal | 5 |
| Nubank | conta corrente | SGML | 102 | UTF-8 | LF | sim | [-9:BRT] | decimal | 5 |
| Sicoob | conta corrente | SGML | 102 | ASCII | CRLF+LF | não | - | decimal | 2 |
| Sicoob | conta corrente | SGML | 102 | Win-1252 | CRLF+LF | sim | sem fuso | decimal | 2 |
| BTG | conta corrente | SGML | 102 | ASCII | LF | não | [-9:GMT] | inteiro | 1 |
| Inter | conta corrente | SGML | 102 | UTF-8 | CRLF | sim | sem fuso | decimal | 1 |
| Sicoob | conta corrente | SGML | 102 | ASCII | LF | não | - | decimal | 1 |
| Sicoob | conta corrente | SGML | 102 | ASCII | LF | sim | [-9:BRT] | inteiro | 1 |

## Bancos

| Banco | Arquivos | Transações | Pastas | Período |
|---|---|---|---|---|
| Itaú | 4695 | 242.353 | 28 | 20220302–20261007 |
| Sicoob | 2148 | 46.339 | 13 | 20220801–20261007 |
| Bradesco | 1251 | 94.841 | 11 | 20220628–20261007 |
| Santander | 289 | 16.879 | 5 | 20220908–20260319 |
| Sicredi | 259 | 15.812 | 4 | 20240601–20261006 |
| Inter | 212 | 11.796 | 7 | 20240101–20261004 |
| C6 | 81 | 7.029 | 3 | 20230801–20261001 |
| BTG | 16 | 194 | 1 | 20240601–20250531 |
| Banco do Brasil | 8 | 420 | 1 | 20240430–20240930 |
| Nubank | 5 | 491 | 1 | 20250301–20250817 |

## Tags por banco

- **BTG**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `ORG`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Banco do Brasil**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CHECKNUM`, `CODE`, `CURDEF`, `DTACCTUP`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `MKTGINFO`, `OFX`, `ORG`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Bradesco**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **C6**: `ACCTID`, `ACCTTYPE`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `BRANCHID`, `CODE`, `CURDEF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `MEMO`, `OFX`, `ORG`, `REFNUM`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Inter**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `BRANCHID`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `NAME`, `OFX`, `ORG`, `REFNUM`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Itaú**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Nubank**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `BRANCHID`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `ORG`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Santander**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `ORG`, `PAYEEID`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Sicoob**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `BRANCHID`, `CHECKNUM`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `NAME`, `OFX`, `ORG`, `REFNUM`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`
- **Sicredi**: `ACCTID`, `ACCTTYPE`, `BALAMT`, `BANKACCTFROM`, `BANKID`, `BANKMSGSRSV1`, `BANKTRANLIST`, `CODE`, `CURDEF`, `DTASOF`, `DTEND`, `DTPOSTED`, `DTSERVER`, `DTSTART`, `FI`, `FID`, `FITID`, `LANGUAGE`, `LEDGERBAL`, `MEMO`, `OFX`, `ORG`, `REFNUM`, `SEVERITY`, `SIGNONMSGSRSV1`, `SONRS`, `STATUS`, `STMTRS`, `STMTTRN`, `STMTTRNRS`, `TRNAMT`, `TRNTYPE`, `TRNUID`

## Pastas

| Pasta | Arquivos | Bancos |
|---|---|---|
| (raiz) | 97 | Itaú, Sicoob, Sicredi |
| movements | 1444 | Bradesco, C6, Itaú, Santander, Sicoob |
| movements/1 | 173 | Itaú |
| movements/4 | 1284 | Itaú, Sicoob |
| movements/5 | 638 | Itaú |
| movements/7 | 129 | Itaú |
| movements/8 | 261 | Itaú |
| movements/9 | 155 | Itaú |
| movements/10 | 69 | Itaú |
| movements/11 | 20 | Itaú, Santander |
| movements/15 | 284 | Itaú |
| movements/16 | 642 | Santander, Sicoob |
| movements/36 | 70 | Itaú |
| movements/37 | 129 | Itaú |
| movements/39 | 176 | Itaú |
| movements/40 | 95 | Itaú |
| movements/42 | 407 | Bradesco, Itaú, Sicoob |
| movements/43 | 334 | Bradesco |
| movements/58 | 67 | Itaú |
| movements/59 | 178 | Bradesco |
| movements/61 | 273 | Bradesco, Itaú, Sicoob |
| movements/64 | 40 | Bradesco, Sicoob |
| movements/65 | 40 | Itaú, Sicoob |
| movements/66 | 112 | C6, Itaú |
| movements/68 | 2 | Sicoob |
| movements/70 | 25 | Sicoob |
| movements/72 | 158 | Bradesco |
| movements/73 | 2 | Itaú |
| movements/75 | 101 | Bradesco, Itaú |
| movements/77 | 8 | Banco do Brasil |
| movements/78 | 161 | Itaú |
| movements/80 | 40 | Bradesco |
| movements/81 | 23 | Santander |
| movements/82 | 13 | Bradesco, Sicoob |
| movements/86 | 28 | Inter |
| movements/91 | 24 | Inter |
| movements/95 | 10 | Sicoob |
| movements/98 | 696 | Santander, Sicoob, Sicredi |
| movements/100 | 42 | Inter |
| movements/102 | 169 | BTG, Bradesco, Sicredi |
| movements/103 | 37 | Inter |
| movements/106 | 7 | Itaú |
| movements/108 | 20 | Itaú |
| movements/109 | 7 | Itaú |
| movements/112 | 5 | Nubank |
| movements/113 | 56 | Inter |
| movements/117 | 165 | Inter, Itaú |
| movements/118 | 3 | C6 |
| movements/119 | 24 | Inter, Itaú |
| movements/121 | 21 | Sicredi |
