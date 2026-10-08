#!/usr/bin/env python3
"""Gera o inventário dos arquivos OFX em exemplos/ (recursivo).

Saídas:
  inventario-exemplos.md   resumo: perfis, bancos, tags, pastas
  inventario-exemplos.csv  uma linha por arquivo; usado para comparar com a execução anterior

Uso: python3 scripts/inventario.py
"""

import csv
import hashlib
import re
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SAMPLES = ROOT / "exemplos"
OUTPUT_MD = ROOT / "inventario-exemplos.md"
OUTPUT_CSV = ROOT / "inventario-exemplos.csv"

BANKS = {
    1: "Banco do Brasil", 33: "Santander", 41: "Banrisul", 70: "BRB", 77: "Inter",
    85: "Ailos", 104: "Caixa", 208: "BTG", 212: "Original", 237: "Bradesco",
    260: "Nubank", 290: "PagBank", 336: "C6", 341: "Itaú", 422: "Safra",
    655: "Votorantim", 748: "Sicredi", 756: "Sicoob",
}

STATEMENT_TYPES = {
    "STMTRS": "conta corrente",
    "CCSTMTRS": "cartão",
    "INVSTMTRS": "investimento",
}

COLUMNS = [
    "Arquivo", "Pasta", "Banco", "BANKID", "Tipo", "Formato", "Versão", "Bytes",
    "Codificação", "Quebra de linha", "Tags fechadas", "Fuso", "Transações",
    "Linhas de saldo", "BALAMT", "Início", "Fim", "SHA-256", "Duplicata de",
]

PROFILE_KEYS = [
    "Banco", "Tipo", "Formato", "Versão", "Codificação", "Quebra de linha",
    "Tags fechadas", "Fuso", "BALAMT",
]


def tag(data, name):
    m = re.search(rb"<" + name + rb">([^<\r\n]*)", data)
    return m.group(1).strip().decode("latin-1") if m else None


def encoding(data):
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError:
        return "Win-1252"
    if text.startswith("\ufeff"):
        return "UTF-8 BOM"
    return "ASCII" if text.isascii() else "UTF-8"


def line_endings(data):
    crlf = data.count(b"\r\n")
    lf = data.count(b"\n") - crlf
    cr = data.count(b"\r") - crlf
    kinds = [k for k, n in (("CRLF", crlf), ("LF", lf), ("CR", cr)) if n]
    return "+".join(kinds) or "-"


def bank_name(bank_id):
    digits = re.sub(r"\D", "", bank_id or "")
    if not digits:
        return "?"
    return BANKS.get(int(digits), f"?({bank_id})")


def tz_shape(raw):
    """[-03:EST] -> [-99:EST]; mantém o nome do fuso, generaliza os dígitos."""
    return re.sub(r"\d", "9", raw)


def analyze(path):
    data = path.read_bytes()
    head = data[:1000]

    if b"<OFX" not in data.upper():
        fmt = "não-OFX"
    elif head.lstrip(b"\xef\xbb\xbf \r\n\t").startswith(b"<?xml") or b"<?OFX" in head:
        fmt = "XML"
    else:
        fmt = "SGML"

    version = re.search(rb"^\s*VERSION\s*:\s*(\d+)", data, re.M) or re.search(rb'<\?OFX[^>]*VERSION="(\d+)"', data)
    tz = re.search(rb"<DTPOSTED>\d+(\[[^\]]*\])?", data)
    balance = tag(data, b"BALAMT")
    bank_id = tag(data, b"BANKID")
    types = [label for t, label in STATEMENT_TYPES.items() if b"<" + t.encode() + b">" in data]
    rel = path.relative_to(SAMPLES)

    return {
        "Arquivo": str(rel),
        "Pasta": str(rel.parent) if rel.parent != Path(".") else "",
        "Banco": bank_name(bank_id) if bank_id else (tag(data, b"ORG") or "?"),
        "BANKID": bank_id or "",
        "Tipo": "+".join(types) or "?",
        "Formato": fmt,
        "Versão": version.group(1).decode() if version else "-",
        "Bytes": str(len(data)),
        "Codificação": encoding(data),
        "Quebra de linha": line_endings(data),
        "Tags fechadas": "sim" if b"</TRNAMT>" in data else "não",
        "Fuso": tz_shape(tz.group(1).decode()) if tz and tz.group(1) else ("sem fuso" if tz else "-"),
        "Transações": str(data.count(b"<STMTTRN>")),
        "Linhas de saldo": str(len(re.findall(rb"<MEMO>\s*(?:SALDO|SDO )", data))),
        "BALAMT": "-" if balance is None else ("decimal" if re.search(r"[.,]", balance) else "inteiro"),
        "Início": (tag(data, b"DTSTART") or "")[:8],
        "Fim": (tag(data, b"DTEND") or "")[:8],
        "SHA-256": hashlib.sha256(data).hexdigest(),
        "Duplicata de": "",
        "_tags": {t.decode() for t in re.findall(rb"<([A-Z0-9.]+)>", data)},
    }


def previous_inventory():
    if not OUTPUT_CSV.exists():
        return {}
    with OUTPUT_CSV.open(newline="", encoding="utf-8") as f:
        return {r["Arquivo"]: r["SHA-256"] for r in csv.DictReader(f)}


def table(headers, rows):
    lines = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
    lines += ["| " + " | ".join(str(c) for c in r) + " |" for r in rows]
    return "\n".join(lines)


def main():
    previous = previous_inventory()
    files = sorted(
        p for p in SAMPLES.rglob("*")
        if p.is_file() and not any(part.startswith(".") for part in p.relative_to(SAMPLES).parts)
    )
    items = [analyze(p) for p in files]

    first_by_hash = {}
    for it in items:
        h = it["SHA-256"]
        it["Duplicata de"] = first_by_hash.get(h, "")
        first_by_hash.setdefault(h, it["Arquivo"])
    unique = [it for it in items if not it["Duplicata de"]]

    known_hashes = set(previous.values())
    new = [it for it in items if it["Arquivo"] not in previous]
    new_content = [it for it in new if it["SHA-256"] not in known_hashes and not it["Duplicata de"]]
    removed = sorted(set(previous) - {it["Arquivo"] for it in items})

    profiles = Counter(tuple(it[k] for k in PROFILE_KEYS) for it in unique)
    tags_by_bank = defaultdict(set)
    banks = defaultdict(lambda: {"arquivos": 0, "transações": 0, "pastas": set(), "início": "99999999", "fim": ""})
    folders = defaultdict(lambda: {"arquivos": 0, "bancos": set(), "contas": set()})
    for it in unique:
        tags_by_bank[it["Banco"]] |= it["_tags"]
        b = banks[it["Banco"]]
        b["arquivos"] += 1
        b["transações"] += int(it["Transações"])
        b["pastas"].add(it["Pasta"] or "(raiz)")
        if it["Início"]:
            b["início"] = min(b["início"], it["Início"])
        b["fim"] = max(b["fim"], it["Fim"])
        f = folders[it["Pasta"] or "(raiz)"]
        f["arquivos"] += 1
        f["bancos"].add(it["Banco"])

    profile_table = table(
        PROFILE_KEYS + ["Arquivos"],
        [list(k) + [n] for k, n in sorted(profiles.items(), key=lambda kv: (-kv[1], kv[0]))],
    )

    out = [
        "# Inventário de exemplos OFX",
        "",
        f"Gerado em {datetime.now():%Y-%m-%d %H:%M} — {len(items)} arquivos em `exemplos/` "
        f"({len(unique)} únicos, {len(items) - len(unique)} duplicados por conteúdo).",
        "",
        "Regenerar com `python3 scripts/inventario.py`. Detalhe por arquivo em `inventario-exemplos.csv`.",
        "Duplicados por conteúdo ficam fora das contagens abaixo.",
        "",
        "## Perfis encontrados",
        "",
        profile_table,
        "",
        "## Bancos",
        "",
        table(
            ["Banco", "Arquivos", "Transações", "Pastas", "Período"],
            [
                [name, b["arquivos"], f"{b['transações']:,}".replace(",", "."), len(b["pastas"]),
                 f"{b['início']}–{b['fim']}" if b["fim"] else "-"]
                for name, b in sorted(banks.items(), key=lambda kv: -kv[1]["arquivos"])
            ],
        ),
        "",
        "## Tags por banco",
        "",
        *[f"- **{b}**: " + ", ".join(f"`{t}`" for t in sorted(ts)) for b, ts in sorted(tags_by_bank.items())],
        "",
        "## Pastas",
        "",
        table(
            ["Pasta", "Arquivos", "Bancos"],
            [
                [name, f["arquivos"], ", ".join(sorted(f["bancos"]))]
                for name, f in sorted(folders.items(), key=lambda kv: (len(kv[0]), kv[0]))
            ],
        ),
        "",
    ]
    OUTPUT_MD.write_text("\n".join(out), encoding="utf-8")

    with OUTPUT_CSV.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(items)

    print(f"{len(items)} arquivos ({len(unique)} únicos) -> {OUTPUT_MD.name}, {OUTPUT_CSV.name}")
    if previous:
        print(f"novos caminhos: {len(new)} | conteúdo inédito: {len(new_content)} | removidos: {len(removed)}")
        new_profiles = Counter(tuple(it[k] for k in PROFILE_KEYS) for it in new_content)
        if new_profiles:
            print("\nPerfis dos arquivos com conteúdo inédito:")
            print(table(PROFILE_KEYS + ["Arquivos"], [list(k) + [n] for k, n in new_profiles.most_common()]))
        for name in removed[:20]:
            print(f"removido: {name}")
    print("\nPerfis (todos os únicos):")
    print(profile_table)


if __name__ == "__main__":
    main()
