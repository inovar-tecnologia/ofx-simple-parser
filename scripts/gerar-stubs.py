#!/usr/bin/env python3
"""Gera stubs OFX anonimizados a partir de exemplos/ para versionar em tests/Fixtures/ofx/.

Seleção:
  - menor conjunto de arquivos que cobre todas as características encontradas em cada banco
    (codificação, quebra de linha, formato de data, separador decimal, FITID repetido, etc.);
  - um par de exportações sobrepostas da mesma conta por banco, para testar o fingerprint.

Anonimização (só nos valores de ACCTID, BRANCHID, MEMO, NAME, FITID, CHECKNUM, REFNUM,
TRNAMT, BALAMT, TRNUID e PAYEEID):
  - cada sequência de dígitos vira outra de mesmo tamanho, preservando zeros à esquerda;
  - em MEMO, NAME e FITID, palavras que aparecem em menos de PUBLIC_MIN_ACCOUNTS contas
    distintas têm as letras ASCII trocadas (mesmo tamanho e caixa; acentos e bytes de
    codificação quebrada ficam como estão); as demais são mantidas;
  - o mapeamento é determinístico (HMAC com a semente em .stub-seed), então valores iguais
    continuam iguais entre transações e entre arquivos.
  Bytes fora desses valores (cabeçalho, tags, datas, espaços, quebras de linha) não mudam,
  e o arquivo é regravado na mesma codificação de origem.

Substituições: antes da anonimização, cada linha `padrão => substituto` de .stub-substituicoes
(expressão regular, sem diferenciar maiúsculas) é aplicada aos mesmos valores. O substituto
segue a caixa do trecho original (tudo maiúsculo, tudo minúsculo ou como escrito no arquivo)
e nunca é anonimizado. O script falha se algum padrão ainda aparecer em um stub.

A semente (.stub-seed ou variável OFX_STUB_SEED) e .stub-substituicoes não devem ser versionadas.

Uso: python3 scripts/gerar-stubs.py
"""

import hashlib
import hmac
import json
import os
import re
import secrets
import string
import sys
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SAMPLES = ROOT / "exemplos"
OUTPUT = ROOT / "tests" / "Fixtures" / "ofx"
SEED_FILE = ROOT / ".stub-seed"
SUBSTITUTIONS_FILE = ROOT / ".stub-substituicoes"

PUBLIC_MIN_ACCOUNTS = 10
MIN_OVERLAP_DAYS = 3

BANKS = {
    1: "banco-do-brasil", 33: "santander", 41: "banrisul", 70: "brb", 77: "inter",
    85: "ailos", 104: "caixa", 208: "btg", 212: "original", 237: "bradesco",
    260: "nubank", 290: "pagbank", 336: "c6", 341: "itau", 422: "safra",
    655: "votorantim", 748: "sicredi", 756: "sicoob",
}

SENSITIVE_TAGS = [
    "ACCTID", "BRANCHID", "MEMO", "NAME", "FITID", "CHECKNUM", "REFNUM",
    "TRNAMT", "BALAMT", "TRNUID", "PAYEEID",
]
TEXT_TAGS = {"MEMO", "NAME", "FITID"}
VALUE_RE = re.compile(r"(<(" + "|".join(SENSITIVE_TAGS) + r")>)([^<\r\n]*)")
TOKEN_RE = re.compile(r"\d+|[^\W\d_]+")
ENTITY_OR_TOKEN_RE = re.compile(r"&#?\w+;|\d+|[^\W\d_]+")


def decode(data):
    try:
        return data.decode("utf-8"), "utf-8"
    except UnicodeDecodeError:
        return data.decode("latin-1"), "latin-1"


def tag(text, name):
    m = re.search(r"<" + name + r">([^<\r\n]*)", text)
    return m.group(1) if m else None


def extrato_data_nula(valor):
    if valor is None:
        return True
    valor = valor.strip()
    if valor == "":
        return True
    digitos = re.sub(r"\D", "", valor)
    if digitos and re.fullmatch(r"0+", digitos):
        return True
    if re.match(r"^\d{4}-\d{2}-\d{2}T", valor):
        return False
    m = re.match(r"^(\d+)", valor)
    if not m:
        return True
    nucleo = m.group(1)
    if len(nucleo) in (8, 14):
        formato = "%Y%m%d%H%M%S" if len(nucleo) == 14 else "%Y%m%d"
        try:
            datetime.strptime(nucleo, formato)
            return False
        except ValueError:
            return True
    return True


def tags(text, name):
    return re.findall(r"<" + name + r">([^<\r\n]*)", text)


def bank_id(text):
    digits = re.sub(r"\D", "", tag(text, "BANKID") or "")
    return int(digits) if digits else None


def bank_slug(text):
    bid = bank_id(text)
    return BANKS.get(bid, f"banco-{bid}") if bid is not None else "desconhecido"


def account_key(text):
    return f"{bank_id(text)}|{(tag(text, 'ACCTID') or '').strip()}"


def shape(value):
    return re.sub(r"\d", "9", value).replace(" ", "␠")


def cents(amount):
    a = amount.strip().replace(",", ".")
    negative = a.startswith("-")
    integer, _, fraction = a.lstrip("+-").partition(".")
    value = int(integer or 0) * 100 + int((fraction + "00")[:2])
    return -value if negative else value


def full_days(text):
    """Dias com transações dentro do período, sem as bordas nem o dia da geração."""
    start = re.sub(r"\D", "", tag(text, "DTSTART") or "")[:8]
    end = re.sub(r"\D", "", tag(text, "DTEND") or "")[:8]
    server = re.sub(r"\D", "", tag(text, "DTSERVER") or "")[:8]
    days = {re.sub(r"\D", "", d)[:8] for d in tags(text, "DTPOSTED")}
    return {d for d in days if start < d < end and d < server}


def features(data, text):
    f = set()

    try:
        utf8 = data.decode("utf-8")
        f.add("codificacao:" + ("utf-8-bom" if utf8.startswith("\ufeff") else "ascii" if utf8.isascii() else "utf-8"))
    except UnicodeDecodeError:
        f.add("codificacao:win-1252")

    crlf = data.count(b"\r\n")
    lf = data.count(b"\n") - crlf
    f.add("quebra-de-linha:" + ("+".join(k for k, n in (("crlf", crlf), ("lf", lf)) if n) or "nenhuma"))
    f.add("tags-fechadas:" + ("sim" if "</TRNAMT>" in text else "nao"))

    head = text[:1000]
    if "<?xml" in head or "<?OFX" in head:
        f.add("cabecalho:xml")
    elif re.search(r"^\s*VERSION\s*:\s+\d", head, re.M):
        f.add("cabecalho:com-espacos")
    elif re.search(r"^VERSION:\d", head, re.M):
        f.add("cabecalho:padrao")
    else:
        f.add("cabecalho:ausente")
    if re.search(r"^[ \t]+<", text, re.M):
        f.add("indentado")

    for d in set(tags(text, "DTPOSTED")):
        f.add("data:" + shape(d))
    f.add("data-geracao:" + shape(tag(text, "DTSERVER") or ""))

    for a in tags(text, "TRNAMT"):
        a = a.strip()
        f.add("valor-separador:" + ("virgula" if "," in a else "ponto" if "." in a else "nenhum"))
        m = re.search(r"[.,](\d*)$", a)
        if m and len(m.group(1)) == 1:
            f.add("valor:uma-casa-decimal")

    balance = tag(text, "BALAMT")
    f.add("saldo:" + ("ausente" if balance is None else "decimal" if re.search(r"[.,]", balance) else "inteiro"))
    if re.search(r"<MEMO>\s*(SALDO|SDO )", text):
        f.add("linhas-de-saldo")

    names = tags(text, "NAME")
    if names:
        f.add("name:presente")
    if any(not n.strip() for n in names):
        f.add("name:vazio")
    if "<REFNUM>" in text:
        f.add("refnum:presente")

    fitids = [x.strip() for x in tags(text, "FITID")]
    if any(n > 1 for n in Counter(fitids).values()):
        f.add("fitid:repetido")
    if any(re.fullmatch(r"0+", x) for x in fitids):
        f.add("fitid:zeros")
    if any(re.search(r"[A-Za-z]", x) for x in fitids):
        f.add("fitid:alfanumerico")

    if "<STMTTRN>" not in text:
        f.add("sem-transacoes")
    if re.search("[ÃÂ][\x80-\xbf]", text):
        f.add("texto:dupla-codificacao")
    for t in set(x.strip() for x in tags(text, "TRNTYPE")):
        f.add("tipo:" + t)
    f.add("moeda:" + (tag(text, "CURDEF") or "").strip())
    return f


def load_seed():
    seed = os.environ.get("OFX_STUB_SEED")
    if seed:
        return seed.encode()
    if not SEED_FILE.exists():
        SEED_FILE.write_text(secrets.token_hex(32))
        print(f"semente criada em {SEED_FILE.name} (não versionar)")
    return SEED_FILE.read_text().strip().encode()


def load_substitutions():
    if not SUBSTITUTIONS_FILE.exists():
        return []
    rules = []
    for line in SUBSTITUTIONS_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        pattern, _, replacement = line.partition("=>")
        rules.append((re.compile(pattern.strip(), re.I), replacement.strip()))
    return rules


def follow_case(original, replacement):
    if original.isupper():
        return replacement.upper()
    if original.islower():
        return replacement.lower()
    return replacement


class Anonymizer:
    def __init__(self, seed, public_words, substitutions):
        self.seed = seed
        self.substitutions = substitutions
        self.public_words = public_words | {
            tok.upper() for _, r in substitutions for tok in TOKEN_RE.findall(r) if not tok.isdigit()
        }

    def substitute(self, value):
        for pattern, replacement in self.substitutions:
            value = pattern.sub(lambda m: follow_case(m.group(), replacement), value)
        return value

    def stream(self, kind, token, size):
        out, i = b"", 0
        while len(out) < size:
            out += hmac.new(self.seed, f"{kind}:{i}:{token}".encode(), hashlib.sha256).digest()
            i += 1
        return out[:size]

    def digits(self, run):
        if set(run) == {"0"}:
            return run
        zeros = len(run) - len(run.lstrip("0"))
        rest = run[zeros:]
        s = self.stream("d", run, len(rest))
        out = [str(b % 10) for b in s]
        out[0] = str(1 + s[0] % 9)
        return "0" * zeros + "".join(out)

    def word(self, word, allow_public):
        if allow_public and word.upper() in self.public_words:
            return word
        s = self.stream("w", word.upper(), len(word))
        out = []
        for c, b in zip(word, s):
            if not c.isascii():
                out.append(c)
                continue
            ch = string.ascii_uppercase[b % 26]
            out.append(ch if c.isupper() else ch.lower())
        return "".join(out)

    def value(self, tag_name, value):
        allow_public = tag_name in TEXT_TAGS

        def replace(m):
            tok = m.group()
            if tok.startswith("&"):
                return tok
            return self.digits(tok) if tok.isdigit() else self.word(tok, allow_public)

        return ENTITY_OR_TOKEN_RE.sub(replace, self.substitute(value))

    def anonymize(self, data):
        text, encoding = decode(data)
        out = VALUE_RE.sub(lambda m: m.group(1) + self.value(m.group(2), m.group(3)), text)
        return out.encode(encoding)


def sensitive_tokens(text, public_words):
    found = set()
    for m in VALUE_RE.finditer(text):
        for tok in TOKEN_RE.findall(m.group(3)):
            if tok.isdigit():
                if len(tok) >= 5 and set(tok) != {"0"}:
                    found.add(tok)
            elif len(tok) >= 4 and not (m.group(2) in TEXT_TAGS and tok.upper() in public_words):
                found.add(tok.upper())
    return found


def main():
    if not SAMPLES.is_dir():
        sys.exit(f"pasta {SAMPLES} não encontrada")

    seen_hashes = set()
    files = []
    for path in sorted(SAMPLES.rglob("*")):
        if not path.is_file() or any(p.startswith(".") for p in path.relative_to(SAMPLES).parts):
            continue
        data = path.read_bytes()
        digest = hashlib.sha256(data).hexdigest()
        if digest in seen_hashes or b"<OFX" not in data.upper():
            continue
        seen_hashes.add(digest)
        text, _ = decode(data)
        files.append({"path": path, "data": data, "text": text, "bank": bank_slug(text), "account": account_key(text)})
    print(f"{len(files)} arquivos OFX únicos em {SAMPLES.name}/")

    accounts_by_word = defaultdict(set)
    for f in files:
        for name in TEXT_TAGS:
            for value in tags(f["text"], name):
                for tok in TOKEN_RE.findall(value):
                    if not tok.isdigit():
                        accounts_by_word[tok.upper()].add(f["account"])
    public_words = {w for w, accts in accounts_by_word.items() if len(accts) >= PUBLIC_MIN_ACCOUNTS}
    print(f"{len({f['account'] for f in files})} contas | {len(public_words)} palavras públicas (>= {PUBLIC_MIN_ACCOUNTS} contas)")

    for f in files:
        f["features"] = {f"{f['bank']}|{x}" for x in features(f["data"], f["text"])}

    selected, covered = [], set()
    while True:
        best, best_score = None, 0.0
        for f in files:
            gain = len(f["features"] - covered)
            score = gain / (1 + len(f["data"]) / 20000)
            if gain and score > best_score:
                best, best_score = f, score
        if not best:
            break
        selected.append(best)
        covered |= best["features"]

    groups = {}
    by_account = defaultdict(list)
    for f in files:
        by_account[f["account"]].append(f)
    best_pair = {}
    for account, fs in by_account.items():
        fs = sorted(fs, key=lambda f: tag(f["text"], "DTSTART") or "")
        for a, b in zip(fs, fs[1:]):
            overlap = full_days(a["text"]) & full_days(b["text"])
            if len(overlap) < MIN_OVERLAP_DAYS:
                continue
            size = len(a["data"]) + len(b["data"])
            if a["bank"] not in best_pair or size < best_pair[a["bank"]][0]:
                best_pair[a["bank"]] = (size, a, b)
    for bank, (_, a, b) in best_pair.items():
        for f in (a, b):
            groups.setdefault(id(f), []).append(f"sobreposicao-{bank}")
            if all(f is not s for s in selected):
                selected.append(f)

    OUTPUT.mkdir(parents=True, exist_ok=True)
    for old in list(OUTPUT.glob("*.ofx")) + [OUTPUT / "manifest.json"]:
        if old.exists():
            old.unlink()

    substitutions = load_substitutions()
    anonymizer = Anonymizer(load_seed(), public_words, substitutions)
    selected.sort(key=lambda f: (f["bank"], tag(f["text"], "DTSTART") or "", f["path"].name))
    counters, manifest, leaks, total_bytes = Counter(), [], [], 0
    for f in selected:
        counters[f["bank"]] += 1
        name = f"{f['bank']}-{counters[f['bank']]:02d}.ofx"
        stub = anonymizer.anonymize(f["data"])
        (OUTPUT / name).write_bytes(stub)
        total_bytes += len(stub)

        stub_text, _ = decode(stub)
        leaked = sensitive_tokens(f["text"], public_words) & sensitive_tokens(stub_text, public_words)
        leaked |= {m.group() for pattern, _ in substitutions for m in pattern.finditer(stub_text)}
        if leaked:
            leaks.append((name, sorted(leaked)[:5]))

        entry = {
            "arquivo": name,
            "banco": f["bank"],
            "codigoBanco": (tag(stub_text, "BANKID") or "").strip(),
            "numeroConta": (tag(stub_text, "ACCTID") or "").strip(),
            "dataInicio": (tag(stub_text, "DTSTART") or "").strip(),
            "dataFim": (tag(stub_text, "DTEND") or "").strip(),
            "dataGeracao": (tag(stub_text, "DTSERVER") or "").strip(),
            "transacoes": stub_text.count("<STMTTRN>"),
            "somaValoresCentavos": sum(cents(a) for a in tags(stub_text, "TRNAMT")),
            "caracteristicas": sorted(x.split("|", 1)[1] for x in f["features"]),
        }
        if id(f) in groups:
            entry["grupos"] = groups[id(f)]
        datas_nulas = [
            nome
            for nome in ("DTSERVER", "DTSTART", "DTEND")
            if extrato_data_nula(tag(stub_text, nome))
        ]
        if datas_nulas:
            entry["datasNulas"] = datas_nulas
        manifest.append(entry)

    (OUTPUT / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    all_features = set().union(*(f["features"] for f in files))
    print(f"{len(selected)} stubs ({total_bytes / 1024:.0f} KB) em {OUTPUT.relative_to(ROOT)}/ "
          f"cobrindo {len(covered)}/{len(all_features)} características por banco")
    print("por banco: " + ", ".join(f"{b}={n}" for b, n in sorted(counters.items())))
    print("pares sobrepostos: " + (", ".join(sorted(best_pair)) or "nenhum"))
    if leaks:
        print("\nATENÇÃO: valores originais encontrados nos stubs:")
        for name, toks in leaks:
            print(f"  {name}: {toks}")
        sys.exit(1)
    print("verificação de vazamento: ok")


if __name__ == "__main__":
    main()
