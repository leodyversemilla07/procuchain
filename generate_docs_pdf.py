#!/usr/bin/env python3
"""Generate 2-column A4 PDF of ProcuChain source code (PHP + TSX/TS + Blade).

Only includes source code files — no tests, no generated actions, no node_modules/vendor.
Output: PROCUCHAIN_SOURCE_DOCS.pdf
"""

import os
import re
import subprocess
import sys
from pathlib import Path

PROJECT_ROOT = Path("/home/ubuntu/workspace/procuchain")
OUTPUT_MD = PROJECT_ROOT / "PROCUCHAIN_SOURCE_DOCS.md"
OUTPUT_PDF = PROJECT_ROOT / "PROCUCHAIN_SOURCE_DOCS.pdf"

# ── Source file inclusion rules ──────────────────────────────────────────────

EXCLUDE_DIRS = {
    "node_modules", "vendor", ".git", "storage", "bootstrap",
    "public", "tests", "__pycache__", ".elasticbeanstalk",
}
# Skip generated TS action files
EXCLUDE_PATTERNS = ["resources/js/actions/"]

MAX_LINES_PER_FILE = 200

# Directory groups with section titles — only source code directories
SECTIONS = [
    ("app/Console/Commands", "Console Commands"),
    ("app/Contracts", "Contracts (Interfaces)"),
    ("app/DataTransferObjects", "Data Transfer Objects"),
    ("app/Enums", "Enums"),
    ("app/Events", "Events"),
    ("app/Exceptions", "Exceptions"),
    ("app/Http/Controllers", "HTTP Controllers"),
    ("app/Http/Middleware", "HTTP Middleware"),
    ("app/Http/Requests", "HTTP Requests (Validation)"),
    ("app/Http/Responses", "HTTP Responses"),
    ("app/Jobs", "Jobs"),
    ("app/Libraries", "Libraries"),
    ("app/Listeners", "Listeners"),
    ("app/Mail", "Mail (Mailables)"),
    ("app/Models", "Eloquent Models"),
    ("app/Notifications", "Notifications"),
    ("app/Policies", "Authorization Policies"),
    ("app/Providers", "Service Providers"),
    ("app/Repositories", "Repositories"),
    ("app/Services", "Services"),
    ("database/migrations", "Database Migrations"),
    ("database/seeders", "Database Seeders"),
    ("database/factories", "Database Factories"),
    ("routes", "Routes"),
    ("config", "Configuration"),
    ("resources/views", "Blade Templates"),
    ("resources/js/pages", "React Pages"),
    ("resources/js/components", "React Components"),
    ("resources/js/hooks", "React Hooks"),
    ("resources/js/types", "TypeScript Types"),
    ("resources/js/lib", "TypeScript Libraries"),
    ("resources/js/routes", "Route Definitions (TS)"),
    ("resources/js", "Frontend Root (app.tsx)"),
]

VALID_EXT = {".php", ".ts", ".tsx"}


def should_include(rel_path: str) -> bool:
    parts = rel_path.split("/")
    for part in parts:
        if part in EXCLUDE_DIRS:
            return False
    for pattern in EXCLUDE_PATTERNS:
        if pattern in rel_path:
            return False
    ext = os.path.splitext(rel_path)[1]
    return ext in VALID_EXT


def get_files_for_section(section_dir: str) -> list[str]:
    full_dir = PROJECT_ROOT / section_dir
    if not full_dir.exists():
        return []
    files = []
    for root, dirs, filenames in os.walk(full_dir):
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
        for fname in sorted(filenames):
            fpath = os.path.join(root, fname)
            rel_path = os.path.relpath(fpath, PROJECT_ROOT)
            if should_include(rel_path):
                files.append(rel_path)
    return sorted(files)


def read_source(rel_path: str) -> str:
    full_path = PROJECT_ROOT / rel_path
    try:
        with open(full_path, "r", encoding="utf-8", errors="replace") as f:
            lines = f.readlines()
    except (OSError, IOError):
        return "*File not readable*\n"
    if len(lines) > MAX_LINES_PER_FILE:
        content = "".join(lines[:MAX_LINES_PER_FILE])
        content += f"\n// ... truncated ({len(lines)} total lines, showing {MAX_LINES_PER_FILE})\n"
    else:
        content = "".join(lines)
    return content


def detect_language(rel_path: str) -> str:
    if rel_path.endswith(".tsx"):
        return "tsx"
    if rel_path.endswith(".ts"):
        return "ts"
    if rel_path.endswith(".php"):
        return "php"
    return "text"


def generate_markdown() -> str:
    parts = []

    # YAML front matter for pandoc — no backslashes here (they go in the .tex include file)
    parts.append("---")
    parts.append('title: "ProcuChain Source Code Documentation"')
    parts.append('subtitle: "PH Government Procurement Blockchain (RA 12009)"')
    parts.append("author: Leodyver Semilla")
    parts.append("date: May 2026")
    parts.append("documentclass: article")
    parts.append("classoption:")
    parts.append("  - twocolumn")
    parts.append("  - 10pt")
    parts.append("geometry:")
    parts.append("  - a4paper")
    parts.append("  - margin=0.5in")
    parts.append("fontsize: 6.5pt")
    parts.append("mainfont: DejaVu Sans")
    parts.append("monofont: DejaVu Sans Mono")
    parts.append('monofontoptions: "Scale=0.50"')
    parts.append("toc: true")
    parts.append("toc-depth: 3")
    parts.append("colorlinks: true")
    parts.append("linkcolor: NavyBlue")
    parts.append("urlcolor: NavyBlue")
    parts.append("---")
    parts.append("")

    # Compact overview
    parts.append("# Overview")
    parts.append("")
    parts.append("- **Stack:** Laravel 13 + Inertia v3 + React 19 + shadcn/ui + MultiChain CE")
    parts.append("- **Backend:** PHP 8.4, MultiChain blockchain, AWS Elastic Beanstalk")
    parts.append("- **Frontend:** TypeScript, React 19, Inertia.js v3, Tailwind CSS v4")
    parts.append("")

    # File index
    parts.append("# File Index")
    parts.append("")
    total_files = 0
    total_lines = 0

    for section_dir, section_title in SECTIONS:
        files = get_files_for_section(section_dir)
        if not files:
            continue
        section_lines = 0
        for f in files:
            try:
                with open(PROJECT_ROOT / f, "r", encoding="utf-8", errors="replace") as fh:
                    section_lines += sum(1 for _ in fh)
            except:
                pass
        total_files += len(files)
        total_lines += section_lines
        parts.append(f"## {section_title} ({len(files)} files, {section_lines:,} lines)")
        parts.append("")
        for f in files:
            short = f.replace("resources/js/", "").replace("app/", "").replace("resources/views/", "")
            parts.append(f"- `{short}`")
        parts.append("")

    parts.append(f"**Total: {total_files} files, {total_lines:,} lines**")
    parts.append("")
    parts.append("---")
    parts.append("")

    # Full source code
    parts.append("# Source Code")
    parts.append("")

    for section_dir, section_title in SECTIONS:
        files = get_files_for_section(section_dir)
        if not files:
            continue
        parts.append(f"## {section_title}")
        parts.append("")
        for rel_path in files:
            lang = detect_language(rel_path)
            content = read_source(rel_path)
            parts.append(f"### {rel_path}")
            parts.append("")
            parts.append(f"```{lang}")
            parts.append(content.rstrip())
            parts.append("```")
            parts.append("")
        parts.append("---")
        parts.append("")

    return "\n".join(parts)


def generate_pdf(markdown_content: str) -> bool:
    OUTPUT_MD.write_text(markdown_content, encoding="utf-8")
    print(f"Markdown written: {OUTPUT_MD.stat().st_size:,} bytes")

    cmd = [
        "pandoc",
        str(OUTPUT_MD),
        "-f", "markdown+yaml_metadata_block",
        "-t", "pdf",
        "--pdf-engine=xelatex",
        f"--output={OUTPUT_PDF}",
        "--standalone",
        "--include-in-header=/tmp/procuchain_header.tex",
    ]

    print("Running pandoc → XeLaTeX (this may take a few minutes)...")
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=600)

    if result.returncode != 0:
        print(f"Pandoc error (exit {result.returncode}):")
        print(result.stderr[-3000:] if len(result.stderr) > 3000 else result.stderr)
        return False

    if OUTPUT_PDF.exists():
        size = OUTPUT_PDF.stat().st_size
        print(f"PDF generated: {OUTPUT_PDF} ({size:,} bytes)")
        return True
    print("PDF file not found")
    return False


def check_pdf_pages() -> int:
    result = subprocess.run(["pdfinfo", str(OUTPUT_PDF)], capture_output=True, text=True)
    for line in result.stdout.split("\n"):
        if "Pages:" in line:
            return int(line.split(":")[1].strip())
    return 0


def main():
    print("=" * 60)
    print("ProcuChain Source Code → 2-Column PDF")
    print("=" * 60)

    print("\n[1/3] Generating markdown...")
    md = generate_markdown()

    print("\n[2/3] Converting to PDF...")
    success = generate_pdf(md)
    if not success:
        sys.exit(1)

    print("\n[3/3] Verifying...")
    pages = check_pdf_pages()
    size_kb = OUTPUT_PDF.stat().st_size / 1024

    print(f"\n{'=' * 60}")
    print(f"  ProcuChain Source Code Documentation")
    print(f"  Pages: {pages}")
    print(f"  Size:  {size_kb:.0f} KB")
    print(f"  Output: {OUTPUT_PDF}")
    print(f"{'=' * 60}")


if __name__ == "__main__":
    main()
