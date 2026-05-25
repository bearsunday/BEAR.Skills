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
    sql_dir = root / "var" / "db" / "sql"
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
                    f"#[DbQuery('{sql_id}')] has no matching var/db/sql/{sql_id}.sql file.",
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
    sql_dir = root / "var" / "db" / "sql"
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
    findings.extend(find_entity_patterns(root))
    findings.extend(find_query_patterns(root))
    findings.extend(find_sql_patterns(root))
    findings.extend(find_direct_exec(root))
    findings.extend(find_template_projection_patterns(root))
    print_findings(findings)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
