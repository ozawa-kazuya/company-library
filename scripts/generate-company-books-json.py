#!/usr/bin/env python3
"""会社書籍在庫管理.xlsx から company-books.json を生成する。"""

from __future__ import annotations

import json
import re
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path

try:
    import openpyxl
except ImportError:
    print("openpyxl が必要です: pip install -r scripts/requirements.txt", file=sys.stderr)
    sys.exit(1)


def to_isbn13(raw: str) -> str | None:
    isbn = re.sub(r"[^0-9Xx]", "", raw)
    if len(isbn) == 13:
        return isbn
    if len(isbn) == 10:
        core = "978" + isbn[:-1]
        total = sum(int(d) * (1 if i % 2 == 0 else 3) for i, d in enumerate(core))
        return core + str((10 - total % 10) % 10)
    return None


def lookup_ndl(title: str, cache: dict[str, dict]) -> dict:
    if title in cache:
        return cache[title]

    queries = [title]
    simplified = re.sub(r"[（(].*?[）)]", "", title).strip()
    if simplified and simplified != title:
        queries.append(simplified)

    best: dict | None = None
    norm = re.sub(r"\s+", "", title)

    for query in queries:
        params = urllib.parse.urlencode({"title": query, "books": "true", "cnt": 10})
        url = f"https://iss.ndl.go.jp/api/opensearch?{params}"
        req = urllib.request.Request(url, headers={"User-Agent": "CompanyLibrary/1.0"})

        for attempt in range(3):
            try:
                with urllib.request.urlopen(req, timeout=20) as resp:
                    xml = resp.read().decode("utf-8", errors="replace")
                break
            except Exception as exc:
                if attempt == 2:
                    cache[title] = {"error": str(exc)}
                    return cache[title]
                time.sleep(2 ** attempt)

        for item in xml.split("<item>")[1:]:
            match = re.search(r'xsi:type="dcndl:ISBN">([^<]+)', item)
            if not match:
                continue
            isbn = to_isbn13(match.group(1))
            if not isbn:
                continue

            title_match = re.search(r"<dc:title>([^<]+)", item)
            found_title = title_match.group(1) if title_match else title
            found_norm = re.sub(r"\s+", "", found_title)

            if found_norm == norm:
                score = 10
            elif norm in found_norm or found_norm in norm:
                score = 5
            else:
                score = 1

            candidate = {"isbn": isbn, "title": found_title, "score": score}
            if best is None or candidate["score"] > best["score"]:
                best = candidate

        if best and best["score"] >= 5:
            break

        time.sleep(1.2)

    if best:
        result = {"isbn": best["isbn"], "title": best["title"]}
    else:
        result = {"error": "not_found"}

    cache[title] = result
    return result


def main() -> None:
    excel = Path(sys.argv[1] if len(sys.argv) > 1 else "~/Downloads/会社書籍在庫管理.xlsx").expanduser()
    out = Path(__file__).resolve().parents[1] / "database/seeders/data/company-books.json"

    wb = openpyxl.load_workbook(excel, read_only=True, data_only=True)
    ws = wb.active
    rows = [r for r in ws.iter_rows(values_only=True) if r[1]][1:]
    wb.close()

    cache: dict[str, dict] = {}
    entries = []

    for index, row in enumerate(rows, 1):
        title = str(row[1]).strip()
        meta = lookup_ndl(title, cache)
        entries.append(
            {
                "excel_title": title,
                "stock_copies": int(row[3] if row[3] is not None else row[2] or 1),
                "lookup": meta,
            }
        )
        print(f"{index:3}/{len(rows)} {title[:45]:45} -> {meta.get('isbn', meta.get('error', '?'))}")

    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(entries, ensure_ascii=False, indent=2), encoding="utf-8")
    found = sum(1 for entry in entries if "isbn" in entry["lookup"])
    print(f"\nDone: {found}/{len(entries)} ISBNs -> {out}")


if __name__ == "__main__":
    main()
