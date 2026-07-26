#!/usr/bin/env python3
"""Scan a BEAR.Sunday project for candidate clean-style findings.

The scanner is intentionally conservative and dependency-free. It reports
candidates that an agent should confirm by reading source before editing.
"""

from __future__ import annotations

import argparse
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable


@dataclass(frozen=True)
class Finding:
    priority: str
    path: str
    line: int | None
    message: str
    suggestion: str


def php_files(root: Path, *parts: str) -> Iterable[Path]:
    base = root.joinpath(*parts)
    if not base.exists():
        return []
    return sorted(p for p in base.rglob("*.php") if p.is_file())


def template_files(root: Path) -> Iterable[Path]:
    base = root / "templates"
    if not base.exists():
        return []
    suffixes = {".php", ".twig", ".html.twig"}
    return sorted(p for p in base.rglob("*") if p.is_file() and any(str(p).endswith(s) for s in suffixes))


def rel(root: Path, path: Path) -> str:
    try:
        return str(path.relative_to(root))
    except ValueError:
        return str(path)


def line_no(text: str, index: int) -> int:
    return text.count("\n", 0, index) + 1


def find_resource_patterns(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    for path in php_files(root, "src", "Resource"):
        text = path.read_text(errors="ignore")
        for m in re.finditer(r"function\s+on(?:Get|Post|Put|Patch|Delete)\s*\([^)]*\)\s*:\s*ResourceObject\b", text, re.S):
            findings.append(Finding(
                "P2", rel(root, path), line_no(text, m.start()),
                "Resource on* method returns ResourceObject.",
                "Use return type static when the method returns $this.",
            ))
        for m in re.finditer(r"\$this->body\s*\[[^\]]+\]\s*=", text):
            findings.append(Finding(
                "P2", rel(root, path), line_no(text, m.start()),
                "Response body is built through a sequential $this->body[...] assignment.",
                "Prefer one literal $this->body = [...] or $this->body += [...] when #[Embed] injected slots must be preserved.",
            ))
        for m in re.finditer(r"lastInsertId\s*\(", text):
            findings.append(Finding(
                "P1", rel(root, path), line_no(text, m.start()),
                "lastInsertId() is used.",
                "Prefer re-selecting by a client-supplied natural key such as bySlug()/byEmail()/byFilename().",
            ))
    return findings



_CODE_NOT_FOUND_PATTERN = re.compile(
    r"\$this->code\s*=\s*(?:"
    r"404\b"
    r"|(?:\\?[A-Za-z_][A-Za-z0-9_]*\\)*Code::NOT_FOUND\b"
    r"|(?:\\?[A-Za-z_][A-Za-z0-9_]*\\)*StatusCode::NOT_FOUND\b"
    r")"
)


def _template_prefix_before_html(text: str, max_lines: int = 30) -> str:
    """Return the initial PHP-only template prefix before visible output."""
    lines = text.splitlines(keepends=True)[:max_lines]
    prefix: list[str] = []
    in_php = False
    for line in lines:
        stripped = line.strip()
        if not in_php:
            if stripped == "":
                prefix.append(line)
                continue
            if stripped.startswith("<?php"):
                in_php = True
                prefix.append(line)
                if "?>" in line and line.split("?>", 1)[1].strip():
                    break
                continue
            break

        prefix.append(line)
        if "?>" in line:
            break
    return "".join(prefix)


def find_page_template_not_found_guard(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    page_dir = root / "src" / "Resource" / "Page"
    if not page_dir.exists():
        return findings

    for path in sorted(page_dir.glob("*.php")):
        text = path.read_text(errors="ignore")
        if not _CODE_NOT_FOUND_PATTERN.search(text):
            continue

        entity = path.stem
        template_path = root / "templates" / "Page" / f"{entity}.php"
        if not template_path.exists():
            continue

        exception = f"{entity}NotFoundException"
        guard_pattern = re.compile(
            r"throw\s+new\s+(?:\\?[A-Za-z_][A-Za-z0-9_]*\\)*" + re.escape(exception) + r"\b"
        )
        prefix = _template_prefix_before_html(template_path.read_text(errors="ignore"))
        if guard_pattern.search(prefix):
            continue

        findings.append(Finding(
            "P2", rel(root, template_path), None,
            f"NOT_FOUND Page template lacks a top-of-file {exception} guard.",
            f"Throw {exception} before template HTML output when the Page resource sets a 404 status (literal 404 or Code::NOT_FOUND).",
        ))
    return findings


_COMMAND_INTERFACE_PARAM = re.compile(
    r"\bprivate\s+(?:readonly\s+)?(?:\?\s*)?"
    r"(?P<type>(?:\\?[A-Za-z_][A-Za-z0-9_]*\\)*[A-Za-z_][A-Za-z0-9_]*CommandInterface)"
    r"\s+\$(?P<name>[A-Za-z_][A-Za-z0-9_]*)"
)
_GENERIC_COMMAND_PROPERTY_NAMES = {"cmd", "command", "commands"}


def _method_parameter_span(text: str, method_name: str) -> tuple[int, int] | None:
    m = re.search(r"\bfunction\s+" + re.escape(method_name) + r"\s*\(", text)
    if not m:
        return None

    start = text.find("(", m.start())
    depth = 0
    quote: str | None = None
    escape = False
    for i in range(start, len(text)):
        ch = text[i]
        if quote is not None:
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == quote:
                quote = None
            continue
        if ch in {"'", '"'}:
            quote = ch
            continue
        if ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                return start + 1, i
    return None


def find_command_interface_property_names(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    for path in php_files(root, "src", "Resource"):
        text = path.read_text(errors="ignore")
        span = _method_parameter_span(text, "__construct")
        if span is None:
            continue
        params_start, params_end = span
        params = text[params_start:params_end]
        for m in _COMMAND_INTERFACE_PARAM.finditer(params):
            name = m.group("name")
            if name.endswith("Cmd") or name in _GENERIC_COMMAND_PROPERTY_NAMES:
                continue
            type_base = m.group("type").lstrip("\\").split("\\")[-1]
            entity = type_base.removesuffix("CommandInterface")
            expected = f"{entity[:1].lower()}{entity[1:]}Cmd"
            findings.append(Finding(
                "P2", rel(root, path), line_no(text, params_start + m.start()),
                f"Constructor-promoted {type_base} property '${name}' does not use the Cmd suffix.",
                f"Rename the Resource property to ${expected} (or <entity><Role>Cmd for link-table writes) per Resource property naming.",
            ))
    return findings


def _class_body_span(text: str) -> tuple[int, int] | None:
    m = re.search(r"\bclass\s+\w+", text)
    if not m:
        return None
    start = text.find("{", m.end())
    if start < 0:
        return None

    depth = 0
    quote: str | None = None
    escape = False
    for i in range(start, len(text)):
        ch = text[i]
        if quote is not None:
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == quote:
                quote = None
            continue
        if ch in {"'", '"'}:
            quote = ch
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start + 1, i
    return None


def find_private_method_order(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    private_pattern = re.compile(r"^\s*private\s+(?:static\s+)?function\s+\w+\s*\(", re.M)
    public_pattern = re.compile(r"^\s*public\s+(?:static\s+)?function\s+(\w+)\s*\(", re.M)
    for path in php_files(root, "src", "Resource"):
        text = path.read_text(errors="ignore")
        span = _class_body_span(text)
        if span is None:
            continue
        body_start, body_end = span
        body = text[body_start:body_end]
        private_methods = list(private_pattern.finditer(body))
        public_methods = [m for m in public_pattern.finditer(body) if m.group(1) != "__construct"]
        if not private_methods or not public_methods:
            continue
        first_private = private_methods[0]
        last_public = public_methods[-1]
        if first_private.start() >= last_public.start():
            continue
        findings.append(Finding(
            "P3", rel(root, path), line_no(text, body_start + first_private.start()),
            "Private helper method appears before a later public method.",
            "Move private helpers after all public resource methods, keeping __construct before handlers.",
        ))
    return findings

def find_entity_patterns(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    for path in php_files(root, "src", "Entity"):
        text = path.read_text(errors="ignore")
        if "class " not in text:
            continue
        if not re.search(r"final\s+readonly\s+class\s+\w+", text):
            findings.append(Finding(
                "P3", rel(root, path), None,
                "Entity class is not declared as final readonly.",
                "Consider final readonly entity classes when this project follows immutable entity style.",
            ))
    return findings


def dbquery_ids(text: str) -> list[tuple[str, int]]:
    ids: list[tuple[str, int]] = []
    pattern = re.compile(r"#\[\s*DbQuery\s*\(\s*['\"]([^'\"]+)['\"]")
    for m in pattern.finditer(text):
        ids.append((m.group(1), line_no(text, m.start())))
    return ids


def method_names(text: str) -> list[tuple[str, int]]:
    names: list[tuple[str, int]] = []
    for m in re.finditer(r"public\s+function\s+(\w+)\s*\(", text):
        names.append((m.group(1), line_no(text, m.start())))
    return names


def find_query_patterns(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    query_dir = root / "src" / "Query"
    sql_dir = root / "var" / "sql"
    if not query_dir.exists():
        return findings

    write_verbs = {"add", "update", "delete", "clear", "link", "create", "save", "remove"}
    read_verbs = {"item", "list"}

    for path in sorted(query_dir.rglob("*Interface.php")):
        text = path.read_text(errors="ignore")
        filename = path.name
        is_query = filename.endswith("QueryInterface.php")
        is_command = filename.endswith("CommandInterface.php")

        if not (is_query or is_command):
            findings.append(Finding(
                "P3", rel(root, path), None,
                "Interface in src/Query does not end with QueryInterface or CommandInterface.",
                "Use suffixes to make Read/Write roles explicit.",
            ))

        for sql_id, lineno in dbquery_ids(text):
            expected = sql_dir / f"{sql_id}.sql"
            if sql_dir.exists() and not expected.exists():
                findings.append(Finding(
                    "P1", rel(root, path), lineno,
                    f"#[DbQuery('{sql_id}')] has no matching var/sql/{sql_id}.sql file.",
                    "Keep DbQuery id and SQL filename in lock-step.",
                ))
            if not re.fullmatch(r"[a-z][a-z0-9]*(?:_[a-z0-9]+)*", sql_id):
                findings.append(Finding(
                    "P3", rel(root, path), lineno,
                    f"DbQuery id '{sql_id}' is not snake_case.",
                    "Use <entity>_<verb> style ids such as article_item or article_by_slug.",
                ))

        for name, lineno in method_names(text):
            low = name.lower()
            if is_query and (low in write_verbs or low.startswith("delete") or low.startswith("update")):
                findings.append(Finding(
                    "P2", rel(root, path), lineno,
                    f"Read QueryInterface contains write-like method '{name}'.",
                    "Move write operations to <Entity>CommandInterface when applying Read/Write split.",
                ))
            if is_command and (low in read_verbs or low.startswith("by") or low.startswith("list")):
                findings.append(Finding(
                    "P2", rel(root, path), lineno,
                    f"Write CommandInterface contains read-like method '{name}'.",
                    "Move reads to <Entity>QueryInterface when applying Read/Write split.",
                ))
    return findings


def find_sql_patterns(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    sql_dir = root / "var" / "sql"
    if not sql_dir.exists():
        return findings
    for path in sorted(sql_dir.glob("*.sql")):
        stem = path.stem
        if not re.fullmatch(r"[a-z][a-z0-9]*(?:_[a-z0-9]+)*", stem):
            findings.append(Finding(
                "P3", rel(root, path), None,
                "SQL filename is not clean snake_case.",
                "Use <entity>_<verb>.sql, e.g. article_item.sql.",
            ))
    return findings


def _strip_sql_comments(sql: str) -> str:
    sql = re.sub(r"/\*.*?\*/", "", sql, flags=re.S)
    sql = re.sub(r"--[^\n]*", "", sql)
    return sql


def _outer_select_clause(sql: str) -> str | None:
    """Return the column list between the first top-level SELECT and its matching FROM.

    Bails out on UNION, sub-SELECT-only queries, and unbalanced parens.
    """
    text = _strip_sql_comments(sql)
    if re.search(r"\bUNION\b", text, re.I):
        return None
    sel = re.search(r"\bSELECT\b", text, re.I)
    if not sel:
        return None
    i = sel.end()
    depth = 0
    n = len(text)
    while i < n:
        ch = text[i]
        if ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
        elif depth == 0 and text[i:i + 4].upper() == "FROM" and (i + 4 == n or (not text[i + 4].isalnum() and text[i + 4] != "_")):
            return text[sel.end():i]
        i += 1
    return None


def _split_select_columns(clause: str) -> list[str] | None:
    parts: list[str] = []
    buf: list[str] = []
    depth = 0
    for ch in clause:
        if ch == "(":
            depth += 1
            buf.append(ch)
        elif ch == ")":
            depth -= 1
            buf.append(ch)
        elif ch == "," and depth == 0:
            parts.append("".join(buf).strip())
            buf = []
        else:
            buf.append(ch)
    if buf:
        parts.append("".join(buf).strip())
    if depth != 0:
        return None
    return [p for p in parts if p]


_PLAIN_COLUMN = re.compile(
    r"^(?:[A-Za-z_][A-Za-z0-9_]*\s*\.\s*)?"
    r"(?P<name>[A-Za-z_][A-Za-z0-9_]*)"
    r"(?:\s+(?:AS\s+)?(?P<alias>[A-Za-z_][A-Za-z0-9_]*))?"
    r"\s*$",
    re.I,
)


def _column_identifier(expr: str) -> str | None:
    """Return the column name we expect a FETCH_FUNC arg to receive.

    Bails on expressions, function calls, literals, or `*`.
    """
    expr = expr.strip()
    if not expr or "*" in expr or "(" in expr or "'" in expr or '"' in expr:
        return None
    m = _PLAIN_COLUMN.match(expr)
    if not m:
        return None
    return (m.group("alias") or m.group("name")).lower()


def _snake_to_camel(name: str) -> str:
    parts = name.split("_")
    return parts[0] + "".join(p[:1].upper() + p[1:] for p in parts[1:])


def _entity_constructor_params(entity_path: Path) -> list[str] | None:
    text = entity_path.read_text(errors="ignore")
    if not re.search(r"final\s+readonly\s+class\s+\w+", text):
        return None
    ctor = re.search(r"public\s+function\s+__construct\s*\((.*?)\)\s*\{", text, re.S)
    if not ctor:
        return None
    body = ctor.group(1)
    body = re.sub(r"#\[[^\]]*\]", "", body)
    params: list[str] = []
    for line in body.split(","):
        m = re.search(r"\$([A-Za-z_][A-Za-z0-9_]*)", line)
        if m:
            params.append(m.group(1))
    return params or None


_FETCH_FUNC_STEM = re.compile(r"^([a-z][a-z0-9_]*?)_(?:item|list|by_[a-z0-9_]+)$")


def find_select_entity_column_order(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    sql_dir = root / "var" / "sql"
    entity_dir = root / "src" / "Entity"
    if not sql_dir.exists() or not entity_dir.exists():
        return findings

    for path in sorted(sql_dir.glob("*.sql")):
        stem_match = _FETCH_FUNC_STEM.match(path.stem)
        if not stem_match:
            continue
        entity_slug = stem_match.group(1)
        entity_class = entity_slug[:1].upper() + entity_slug[1:].replace("_", "")
        entity_path = entity_dir / f"{entity_class}.php"
        if not entity_path.exists():
            continue

        clause = _outer_select_clause(path.read_text(errors="ignore"))
        if clause is None:
            continue
        columns = _split_select_columns(clause)
        if not columns:
            continue
        column_names: list[str] = []
        for col in columns:
            ident = _column_identifier(col)
            if ident is None:
                column_names = []
                break
            column_names.append(_snake_to_camel(ident))
        if not column_names:
            continue

        params = _entity_constructor_params(entity_path)
        if not params:
            continue

        if column_names == params:
            continue
        if set(column_names) != set(params):
            continue  # different field set — not a pure order issue; skip to avoid noise

        first_diff = next((i for i, (a, b) in enumerate(zip(column_names, params)) if a != b), 0)
        findings.append(Finding(
            "P1", rel(root, path), first_diff + 1,
            f"SELECT column order does not match {entity_class}::__construct parameter order "
            f"(SQL: {', '.join(column_names)} | Entity: {', '.join(params)}).",
            "PDO::FETCH_FUNC binds positionally — reorder SELECT or constructor to match in lock-step.",
        ))
    return findings


def find_direct_exec(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    for path in php_files(root, "src"):
        text = path.read_text(errors="ignore")
        for m in re.finditer(r"->exec\s*\(", text):
            findings.append(Finding(
                "P3", rel(root, path), line_no(text, m.start()),
                "Direct exec() call found in src.",
                "Confirm this does not bypass the MediaQuery getRow/getRowList contract for #[DbQuery] writes.",
            ))
    return findings


def find_generic_exceptions(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    pattern = re.compile(r"throw\s+new\s+\\?(?:LogicException|RuntimeException|InvalidArgumentException)\b")
    for path in php_files(root, "src"):
        text = path.read_text(errors="ignore")
        for m in pattern.finditer(text):
            findings.append(Finding(
                "P2", rel(root, path), line_no(text, m.start()),
                "Generic SPL exception thrown in src.",
                "Define a project-specific Exception\\<DomainName>Exception subclass instead.",
            ))
    return findings


def find_manual_surrogate_key(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    surrogate_pattern = re.compile(r"Header::SURROGATE_KEY")
    invalidate_pattern = re.compile(r"DonutRepositoryInterface[^;]*->\s*invalidateTags\s*\(")
    attr_embed_pattern = re.compile(r"^\s*#\[\s*Embed\s*\(", re.M)
    attr_cacheable_pattern = re.compile(r"^\s*#\[\s*Cacheable\b", re.M)
    for path in php_files(root, "src", "Resource"):
        text = path.read_text(errors="ignore")
        has_cacheable = bool(attr_cacheable_pattern.search(text))
        has_embed = bool(attr_embed_pattern.search(text))
        has_fromassoc = "fromAssoc(" in text
        for m in surrogate_pattern.finditer(text):
            if has_cacheable and not has_fromassoc:
                findings.append(Finding(
                    "P2", rel(root, path), line_no(text, m.start()),
                    "Manual Header::SURROGATE_KEY assignment on a #[Cacheable] resource without fromAssoc().",
                    "Leaf #[Cacheable] resources should not touch SURROGATE_KEY; framework writes the self URI tag automatically.",
                ))
            if has_embed and has_fromassoc:
                findings.append(Finding(
                    "P1", rel(root, path), line_no(text, m.start()),
                    "Resource mixes #[Embed] composition with manual fromAssoc() Surrogate-Key write.",
                    "Pick Shape A (#[Embed] only) or Shape B (fromAssoc only) — assigning SURROGATE_KEY short-circuits the body-walk auto-merge.",
                ))
        for m in invalidate_pattern.finditer(text):
            findings.append(Finding(
                "P2", rel(root, path), line_no(text, m.start()),
                "Resource calls DonutRepositoryInterface::invalidateTags() from a write handler.",
                "RefreshSameCommand already purges the self URI tag on writes to #[Cacheable] resources.",
            ))
    return findings


def find_resource_method_order(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    order = ["onGet", "onPost", "onPut", "onPatch", "onDelete"]
    rank = {name: i for i, name in enumerate(order)}
    method_pattern = re.compile(r"public\s+function\s+(on(?:Get|Post|Put|Patch|Delete))\s*\(")
    for path in php_files(root, "src", "Resource"):
        text = path.read_text(errors="ignore")
        last_rank = -1
        last_name: str | None = None
        for m in method_pattern.finditer(text):
            name = m.group(1)
            this_rank = rank[name]
            if this_rank < last_rank:
                findings.append(Finding(
                    "P3", rel(root, path), line_no(text, m.start()),
                    f"Resource handler {name} appears after {last_name} (out of HTTP-verb order).",
                    "Order public on* handlers as onGet, onPost, onPut, onPatch, onDelete; private helpers come after every public method.",
                ))
            last_rank = this_rank
            last_name = name
    return findings


def find_template_projection_patterns(root: Path) -> list[Finding]:
    findings: list[Finding] = []
    loop_pattern = re.compile(
        r"(?:foreach\s*\([^)]+\)\s*:|foreach\s*\([^)]+\)\s*\{|{%\s*for\s+[^%]+%})",
        re.I,
    )
    display_logic_pattern = re.compile(
        r"->(?:is[A-Z]\w*|summary|publishedAtLabel|statusClass|url|tagsJoined)\s*\("
        r"|DateTimeImmutable|new\s+DateTime|http_build_query|continue\s*;|{%\s*if\s+",
    )
    for path in template_files(root):
        text = path.read_text(errors="ignore")
        for loop in loop_pattern.finditer(text):
            window = text[loop.start(): loop.start() + 1200]
            if display_logic_pattern.search(window):
                findings.append(Finding(
                    "P3", rel(root, path), line_no(text, loop.start()),
                    "Template loop contains domain/display branching or computed presentation data.",
                    "Consider a Level 3 Template Projection Lift: move repeated loop-local domain logic to a typed Result object with named Generator traversal.",
                ))
    return findings


def print_findings(findings: list[Finding]) -> None:
    print("# BEAR clean style scan")
    if not findings:
        print("\nNo candidate findings detected by the heuristic scanner.")
        return

    order = {"P1": 0, "P2": 1, "P3": 2}
    findings = sorted(findings, key=lambda f: (order.get(f.priority, 99), f.path, f.line or 0, f.message))
    print("\n| Priority | Location | Finding | Suggested change |")
    print("|---|---|---|---|")
    for f in findings:
        location = f.path if f.line is None else f"{f.path}:{f.line}"
        print(f"| {f.priority} | `{location}` | {f.message} | {f.suggestion} |")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("root", nargs="?", default=".", help="BEAR.Sunday project root")
    args = parser.parse_args()

    root = Path(args.root).resolve()
    if not root.exists():
        parser.error(f"root does not exist: {root}")

    findings: list[Finding] = []
    findings.extend(find_resource_patterns(root))
    findings.extend(find_page_template_not_found_guard(root))
    findings.extend(find_resource_method_order(root))
    findings.extend(find_command_interface_property_names(root))
    findings.extend(find_private_method_order(root))
    findings.extend(find_entity_patterns(root))
    findings.extend(find_query_patterns(root))
    findings.extend(find_sql_patterns(root))
    findings.extend(find_select_entity_column_order(root))
    findings.extend(find_direct_exec(root))
    findings.extend(find_generic_exceptions(root))
    findings.extend(find_manual_surrogate_key(root))
    findings.extend(find_template_projection_patterns(root))
    print_findings(findings)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
