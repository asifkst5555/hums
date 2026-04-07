from __future__ import annotations

import json
import re
from pathlib import Path


BANG_DIGITS = "০১২৩৪৫৬৭৮৯"
TRANS = str.maketrans(BANG_DIGITS, "0123456789")


def to_ascii_digits(value: str) -> str:
    return (value or "").translate(TRANS)


def sql_quote(value: str) -> str:
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


def clean_space(value: str) -> str:
    value = (value or "").replace("√", " ")
    value = re.sub(r"\s+", " ", value).strip(" ,.-")
    return value


def normalize_union(value: str, last_union: str) -> str:
    value = clean_space(value)
    if not value:
        return last_union
    value = value.replace('ওয়াড রর্ড', 'ওয়ার্ড').replace('ওয়াড', 'ওয়ার্ড').replace('রর্', '')
    value = re.sub(r'\bওয়ার্ড\b\s*$', '', value).strip(' ,')
    if value in {'ওয়ার্ড', 'নং', '০১', '০২', '০৩', '০৪', '০৫', '০৬', '০৭', '০৮', '০৯'}:
        return last_union
    return value


def split_name_addr(value: str) -> tuple[str, str]:
    value = clean_space(value)
    parts = [part.strip() for part in value.split(',') if part.strip()]
    if not parts:
        return '', ''

    # Drop accidental leading ward fragments from the name column.
    while parts and ('ওয়ার্ড' in parts[0] or re.fullmatch(r'[০-৯0-9]+নং', parts[0])):
        parts.pop(0)

    if not parts:
        return '', ''

    name = parts[0]
    addr = ', '.join(parts[1:]) if len(parts) > 1 else ''

    name = re.sub(r'^(হাটহাজারী পৌরসভা|হাটহাজারী সদর ইউনিয়ন)\s*,?\s*', '', name).strip(' ,')
    addr = clean_space(addr)
    return name, addr


def pick_phone(row: dict) -> str:
    for key in ('imam_phone', 'khatib_phone', 'muazzin_phone'):
        value = clean_space(row.get(key, ''))
        if value:
            return value.split(',')[0].strip()
    return ''


def pick_imam(row: dict) -> str:
    for key in ('imam_name', 'khatib_name', 'muazzin_name'):
        value = clean_space(row.get(key, ''))
        if value:
            return value
    return ''



def is_header_noise(name: str, union_name: str, addr: str) -> bool:
    hay = f"{name} {union_name} {addr}"
    bad_tokens = [
        "\u0987\u09b8\u09b2\u09be\u09ae\u09bf\u0995 \u09ab\u09be\u0989\u09a8\u09cd\u09a1\u09c7\u09b6\u09a8",
        "\u09ac\u09be\u0982\u09b2\u09be\u09a6\u09c7\u09b6 \u09b8\u09b0\u0995\u09be\u09b0",
        "\u09ae\u09b8\u099c\u09bf\u09a6\u09c7\u09b0 \u09a8\u09be\u09ae\u09c7\u09b0 \u09a4\u09be\u09b2\u09bf\u0995\u09be",
        "\u099c\u09c7\u09b2\u09be\u0983",
        "\u0989\u09aa\u099c\u09c7\u09b2\u09be\u0983",
        "\u0996\u09a4\u09bf\u09ac\u09c7\u09b0 \u09a8\u09be\u09ae",
        "\u0987\u09ae\u09be\u09ae\u09c7\u09b0 \u09a8\u09be\u09ae",
        "\u09ae\u09cb\u09ac\u09be\u0987\u09b2 \u09a8\u0982",
    ]
    return any(token in hay for token in bad_tokens)

def build_records(rows: list[dict]) -> list[dict]:
    records: list[dict] = []
    last_union = ''

    for row in rows:
        union = normalize_union(row.get('union', ''), last_union)
        if union:
            last_union = union

        name, addr = split_name_addr(row.get('mosque_name_address', ''))
        imam = pick_imam(row)
        phone = pick_phone(row)

        ward = clean_space(row.get('ward', ''))
        if ward:
            addr = f"{ward}, {addr}" if addr else ward

        if not name:
            continue
        if addr in {'??????', '????', '\u0993\u09df\u09be\u09b0\u09cd\u09a1', '\u0993\u09df\u09be\u09a1'}:
            addr = ''
        if is_header_noise(name, union or '', addr):
            continue

        records.append({
            'name': name,
            'union_name': union or 'অজানা ইউনিয়ন',
            'imam': imam,
            'phone': to_ascii_digits(phone),
            'addr': addr,
        })

    unique: list[dict] = []
    seen: set[tuple[str, str, str]] = set()
    for item in records:
        key = (item['name'], item['union_name'], item['addr'])
        if key in seen:
            continue
        seen.add(key)
        unique.append(item)
    return unique


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    rows = json.loads((root / 'tmp' / 'mosque_pdf_rows.json').read_text(encoding='utf-8'))
    records = build_records(rows)

    sql_lines = [
        'SET NAMES utf8mb4;',
        'DELETE FROM mosques;',
    ]

    for rec in records:
        sql_lines.append(
            'INSERT INTO mosques (name, union_name, imam, phone, addr) VALUES '
            f"({sql_quote(rec['name'])}, {sql_quote(rec['union_name'])}, {sql_quote(rec['imam'])}, {sql_quote(rec['phone'])}, {sql_quote(rec['addr'])});"
        )

    out_sql = root / 'tmp' / 'import_mosques_from_pdf.sql'
    out_json = root / 'tmp' / 'import_mosques_preview.json'
    out_sql.write_text('\n'.join(sql_lines) + '\n', encoding='utf-8')
    out_json.write_text(json.dumps(records[:50], ensure_ascii=False, indent=2), encoding='utf-8')

    print(f'records={len(records)}')
    print(f'sql={out_sql}')
    print(f'preview={out_json}')


if __name__ == '__main__':
    main()
