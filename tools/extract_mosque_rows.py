from __future__ import annotations

import csv
import json
import re
from pathlib import Path

from pypdf import PdfReader

from parse_mosque_pdf import convert_bijoy_to_unicode, find_pdf_path


BANG_DIGITS = "০১২৩৪৫৬৭৮৯"
ROW_NO_RE = re.compile(rf"^[{BANG_DIGITS}0-9]{{1,3}}$")
PHONE_RE = re.compile(rf"[{BANG_DIGITS}0-9]{{11,14}}")


def normalize_text(value: str) -> str:
    value = convert_bijoy_to_unicode(value or "")
    value = value.replace("|", "\u0964")
    value = re.sub(r"\s+", " ", value).strip()
    return value


def is_row_number(value: str) -> bool:
    return bool(ROW_NO_RE.fullmatch(value.strip()))


def is_phone(value: str) -> bool:
    return bool(PHONE_RE.fullmatch(value.strip()))


def bucket_name(x: float) -> str:
    if x < 170:
        return "left"
    if x < 360:
        return "mosque"
    if x < 460:
        return "khatib"
    if x < 550:
        return "imam"
    if x < 640:
        return "muazzin"
    return "madrasa"


def collect_tokens(page) -> list[dict]:
    parts: list[dict] = []

    def visitor(text, cm, tm, font_dict, font_size):
        raw = (text or "").strip()
        if not raw:
            return
        parts.append({
            "x": round(float(tm[4]), 1),
            "y": round(float(tm[5]), 1),
            "raw": raw,
            "text": normalize_text(raw),
        })

    page.extract_text(visitor_text=visitor)
    return parts


def group_row_starts(tokens: list[dict]) -> list[dict]:
    y_counts: dict[float, int] = {}
    for token in tokens:
        if token["x"] < 120 and is_row_number(token["text"]):
            y_counts[token["y"]] = y_counts.get(token["y"], 0) + 1

    starts = []
    for token in tokens:
        if token["x"] < 60 and is_row_number(token["text"]) and y_counts.get(token["y"], 0) == 1:
            starts.append(token)

    starts.sort(key=lambda item: -item["y"])
    return starts


def init_row(serial: str) -> dict:
    return {
        "serial": serial,
        "union": "",
        "ward": "",
        "mosque_name_address": [],
        "mosque_type": "",
        "khatib_name": [],
        "khatib_phone": [],
        "imam_name": [],
        "imam_phone": [],
        "muazzin_name": [],
        "muazzin_phone": [],
        "madrasa_present": "",
        "madrasa_name": [],
        "source_chunks": [],
    }


def assign_token(row: dict, token: dict) -> None:
    text = token["text"]
    if not text:
        return
    row["source_chunks"].append({"x": token["x"], "y": token["y"], "text": text})
    bucket = bucket_name(token["x"])

    if bucket == "left":
        if token["x"] < 60 and is_row_number(text):
            return
        if "\u0993\u09df\u09be\u09b0\u09cd\u09a1" in text or "\u0993\u09df\u09be\u09a1" in text or "ward" in text.lower():
            row["ward"] = f"{row['ward']} {text}".strip()
        else:
            row["union"] = f"{row['union']} {text}".strip()
        return

    if bucket == "mosque":
        if text == "?":
            row["mosque_type"] = "\u099c\u09be\u09ae\u09c7 \u09ae\u09b8\u099c\u09bf\u09a6"
            return
        row["mosque_name_address"].append(text)
        return

    if bucket == "khatib":
        if is_phone(text):
            row["khatib_phone"].append(text)
        else:
            row["khatib_name"].append(text)
        return

    if bucket == "imam":
        if is_phone(text):
            row["imam_phone"].append(text)
        else:
            row["imam_name"].append(text)
        return

    if bucket == "muazzin":
        if text == "?":
            row["madrasa_present"] = "\u09b9\u09cd\u09af\u09be\u0981"
            return
        if is_phone(text):
            row["muazzin_phone"].append(text)
        else:
            row["muazzin_name"].append(text)
        return

    if text == "?":
        row["madrasa_present"] = "\u09b9\u09cd\u09af\u09be\u0981"
    elif text in {"\u09a8\u09be", "\u09b9\u09cd\u09af\u09be\u0981"}:
        row["madrasa_present"] = text
    else:
        row["madrasa_name"].append(text)


def finalize_row(row: dict) -> dict:
    def join_lines(values: list[str]) -> str:
        items: list[str] = []
        for value in values:
            value = value.replace("?", "").strip()
            if not value:
                continue
            if not items or items[-1] != value:
                items.append(value)
        return " ".join(items).strip()

    result = {
        "serial": row["serial"],
        "union": row["union"],
        "ward": row["ward"],
        "mosque_name_address": join_lines(row["mosque_name_address"]),
        "mosque_type": row["mosque_type"] or "\u099c\u09be\u09ae\u09c7 \u09ae\u09b8\u099c\u09bf\u09a6",
        "khatib_name": join_lines(row["khatib_name"]),
        "khatib_phone": ", ".join(dict.fromkeys(row["khatib_phone"])),
        "imam_name": join_lines(row["imam_name"]),
        "imam_phone": ", ".join(dict.fromkeys(row["imam_phone"])),
        "muazzin_name": join_lines(row["muazzin_name"]),
        "muazzin_phone": ", ".join(dict.fromkeys(row["muazzin_phone"])),
        "madrasa_present": row["madrasa_present"] or ("\u09b9\u09cd\u09af\u09be\u0981" if row["madrasa_name"] else ""),
        "madrasa_name": join_lines(row["madrasa_name"]),
    }
    return result


def extract_rows() -> list[dict]:
    root = Path(__file__).resolve().parents[1]
    pdf_path = find_pdf_path(root)
    reader = PdfReader(str(pdf_path))

    rows: list[dict] = []
    current_row: dict | None = None

    for page in reader.pages:
        tokens = collect_tokens(page)
        starts = group_row_starts(tokens)
        if not starts:
            if current_row is not None:
                for token in sorted(tokens, key=lambda item: (-item["y"], item["x"])):
                    assign_token(current_row, token)
            continue

        starts_by_y = [start["y"] for start in starts]
        top_y = starts_by_y[0]

        prelude_tokens = sorted([t for t in tokens if t["y"] > top_y], key=lambda item: (-item["y"], item["x"]))
        if current_row is not None:
            for token in prelude_tokens:
                assign_token(current_row, token)

        for index, start in enumerate(starts):
            if current_row is not None:
                rows.append(finalize_row(current_row))
            current_row = init_row(start["text"])
            if index == 0 and prelude_tokens and len(rows) == 0:
                for token in prelude_tokens:
                    assign_token(current_row, token)
            lower_y = starts[index + 1]["y"] if index + 1 < len(starts) else -9999
            window = [
                t for t in tokens
                if t["y"] <= start["y"] and t["y"] > lower_y and not (t["x"] < 60 and is_row_number(t["text"]))
            ]
            for token in sorted(window, key=lambda item: (-item["y"], item["x"])):
                assign_token(current_row, token)

    if current_row is not None:
        rows.append(finalize_row(current_row))

    return rows


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    out_dir = root / "tmp"
    out_dir.mkdir(exist_ok=True)

    rows = extract_rows()

    json_path = out_dir / "mosque_pdf_rows.json"
    csv_path = out_dir / "mosque_pdf_rows.csv"

    json_path.write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8")

    with csv_path.open("w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(
            fh,
            fieldnames=[
                "serial", "union", "ward", "mosque_name_address", "mosque_type",
                "khatib_name", "khatib_phone", "imam_name", "imam_phone",
                "muazzin_name", "muazzin_phone", "madrasa_present", "madrasa_name",
            ],
        )
        writer.writeheader()
        writer.writerows(rows)

    print(f"rows={len(rows)}")
    print(f"json={json_path}")
    print(f"csv={csv_path}")


if __name__ == "__main__":
    main()
