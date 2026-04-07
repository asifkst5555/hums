from __future__ import annotations

import re
from pathlib import Path

from pypdf import PdfReader


PRE_CONVERSION_MAP = {
    "yy": "y",
    "vv": "v",
    "": "",
    "y&": "y",
    "&": "",
    "u": "u",
    "wu": "uw",
    " ,": ",",
    r" \|": r"\|",
    r"\\ ": "",
    r" \\": "",
    r"\\": "",
    r"\n +": "\n",
    r" +\n": "\n",
    "\n\n\n\n\n": "\n\n",
    "\n\n\n\n": "\n\n",
    "\n\n\n": "\n\n",
}

CONVERSION_MAP = {
    "Av": "আ", "A": "অ", "B": "ই", "C": "ঈ", "D": "উ", "E": "ঊ", "F": "ঋ", "G": "এ", "H": "ঐ", "I": "ও", "J": "ঔ",
    "K": "ক", "L": "খ", "M": "গ", "N": "ঘ", "O": "ঙ", "P": "চ", "Q": "ছ", "R": "জ", "S": "ঝ", "T": "ঞ",
    "U": "ট", "V": "ঠ", "W": "ড", "X": "ঢ", "Y": "ণ", "Z": "ত", "_": "থ", "`": "দ", "a": "ধ", "b": "ন",
    "c": "প", "d": "ফ", "e": "ব", "f": "ভ", "g": "ম", "h": "য", "i": "র", "j": "ল", "k": "শ", "l": "ষ",
    "m": "স", "n": "হ", "o": "ড়", "p": "ঢ়", "q": "য়", "r": "ৎ", "s": "ং", "t": "ঃ", "u": "ঁ",
    "0": "০", "1": "১", "2": "২", "3": "৩", "4": "৪", "5": "৫", "6": "৬", "7": "৭", "8": "৮", "9": "৯",
    "•": "ঙ্", "v": "া", "w": "ি", "x": "ী", "y": "ু", "z": "ু", "“": "ু", "–": "ু", "~": "ূ", "ƒ": "ূ", "‚": "ূ",
    "„„": "ৃ", "„": "ৃ", "…": "ৃ", "†": "ে", "‡": "ে", "ˆ": "ৈ", "‰": "ৈ", "Š": "ৗ", r"\|": "।", r"\&": "্‌",
    r"\^": "্ব", "‘": "্তু", "’": "্থ", "‹": "্ক", "Œ": "্ক্র", "”": "চ্", "—": "্ত", "˜": "দ্", "™": "দ্",
    "š": "ন্", "›": "ন্", "œ": "্ন", "Ÿ": "্ব", "¡": "্ব", "¢": "্ভ", "£": "্ভ্র", "¤": "ম্", "¥": "্ম", "¦": "্ব",
    "§": "্ম", "¨": "্য", "©": "র্", "ª": "্র", "«": "্র", "¬": "্ল", "": "্ল", "®": "ষ্", "¯": "স্",
    "°": "ক্ক", "±": "ক্ট", "²": "ক্ষ্ণ", "³": "ক্ত", "´": "ক্ম", "µ": "ক্র", "¶": "ক্ষ", "·": "ক্স", "¸": "গু",
    "¹": "জ্ঞ", "º": "গ্দ", "»": "গ্ধ", "¼": "ঙ্ক", "½": "ঙ্গ", "¾": "জ্জ", "¿": "্ত্র", "À": "জ্ঝ", "Á": "জ্ঞ",
    "Â": "ঞ্চ", "Ã": "ঞ্ছ", "Ä": "ঞ্জ", "Å": "ঞ্ঝ", "Æ": "ট্ট", "Ç": "ড্ড", "È": "ণ্ট", "É": "ণ্ঠ", "Ê": "ণ্ড",
    "Ë": "ত্ত", "Ì": "ত্থ", "Í": "ত্ম", "Î": "ত্র", "Ï": "দ্দ", "Ð": "-", "Ñ": "-", "Ò": '"', "Ó": '"', "Ô": "'",
    "Õ": "'", "Ö": "্র", "×": "দ্ধ", "Ø": "দ্ব", "Ù": "দ্ম", "Ú": "ন্ঠ", "Û": "ন্ড", "Ü": "ন্ধ", "Ý": "ন্স", "Þ": "প্ট",
    "ß": "প্ত", "à": "প্প", "á": "প্স", "â": "ব্জ", "ã": "ব্দ", "ä": "ব্ধ", "å": "ভ্র", "æ": "ম্ন", "ç": "ম্ফ",
    "è": "্ন", "é": "ল্ক", "ê": "ল্গ", "ë": "ল্ট", "ì": "ল্ড", "í": "ল্প", "î": "ল্ফ", "ï": "শু", "ð": "শ্চ",
    "ñ": "শ্ছ", "ò": "ষ্ণ", "ó": "ষ্ট", "ô": "ষ্ঠ", "õ": "ষ্ফ", "ö": "স্খ", "÷": "স্ট", "ø": "স্ন", "ù": "স্ফ",
    "ú": "্প", "û": "হু", "ü": "হৃ", "ý": "হ্ন", "þ": "হ্ম",
}

POST_CONVERSION_MAP = {
    "০ঃ": "০:", "১ঃ": "১:", "২ঃ": "২:", "৩ঃ": "৩:", "৪ঃ": "৪:", "৫ঃ": "৫:", "৬ঃ": "৬:", "৭ঃ": "৭:", "৮ঃ": "৮:", "৯ঃ": "৯:",
    " ঃ": " :", "\nঃ": "\n:", "]ঃ": "]:", "[ঃ": "[:", "অা": "আ", "্‌্‌": "্‌",
}


def do_char_map(text: str, mapping: dict[str, str]) -> str:
    for src, dest in sorted(mapping.items(), key=lambda item: len(item[0]), reverse=True):
        text = re.sub(re.escape(src), dest, text)
    return text


def mb_char_at(text: str, index: int) -> str:
    if index < 0 or index >= len(text):
        return ""
    return text[index]


def is_bangla_pre_kar(ch: str) -> bool:
    return ch in {"ি", "ৈ", "ে"}


def is_bangla_post_kar(ch: str) -> bool:
    return ch in {"া", "ো", "ৌ", "ৗ", "ু", "ূ", "ী", "ৃ"}


def is_bangla_kar(ch: str) -> bool:
    return is_bangla_pre_kar(ch) or is_bangla_post_kar(ch)


def is_bangla_banjonborno(ch: str) -> bool:
    return ch in {
        "ক", "খ", "গ", "ঘ", "ঙ", "চ", "ছ", "জ", "ঝ", "ঞ", "ট", "ঠ", "ড", "ঢ", "ণ", "ত", "থ", "দ", "ধ",
        "ন", "প", "ফ", "ব", "ভ", "ম", "য", "র", "ল", "শ", "ষ", "স", "হ", "ড়", "ঢ়", "য়", "ৎ", "ং", "ঃ", "ঁ",
    }


def is_bangla_nukta(ch: str) -> bool:
    return ch == "ঁ"


def is_bangla_halant(ch: str) -> bool:
    return ch == "্"


def is_space(ch: str) -> bool:
    return ch in {" ", "\t", "\n", "\r"}


def rearrange_unicode_converted_text(text: str) -> str:
    i = 0
    while i < len(text):
        if i < len(text) - 1 and mb_char_at(text, i) == "র" and is_bangla_halant(mb_char_at(text, i + 1)) and not is_bangla_halant(mb_char_at(text, i - 1)):
            j = 1
            while True:
                if i - j < 0:
                    break
                if is_bangla_banjonborno(mb_char_at(text, i - j)) and is_bangla_halant(mb_char_at(text, i - j - 1)):
                    j += 2
                elif j == 1 and is_bangla_kar(mb_char_at(text, i - j)):
                    j += 1
                else:
                    break
            text = text[: i - j] + text[i : i + 2] + text[i - j : i] + text[i + 2 :]
            i += 1
        i += 1

    text = text.replace("্্", "্")

    i = 0
    while i < len(text):
        if (
            i < len(text) - 1
            and mb_char_at(text, i) == "র"
            and is_bangla_halant(mb_char_at(text, i + 1))
            and not is_bangla_halant(mb_char_at(text, i - 1))
            and is_bangla_halant(mb_char_at(text, i + 2))
        ):
            j = 1
            while True:
                if i - j < 0:
                    break
                if is_bangla_banjonborno(mb_char_at(text, i - j)) and is_bangla_halant(mb_char_at(text, i - j - 1)):
                    j += 2
                elif j == 1 and is_bangla_kar(mb_char_at(text, i - j)):
                    j += 1
                else:
                    break
            text = text[: i - j] + text[i : i + 2] + text[i - j : i] + text[i + 2 :]
            i += 1

        if i > 0 and i < len(text) - 1 and mb_char_at(text, i) == "্" and (is_bangla_kar(mb_char_at(text, i - 1)) or is_bangla_nukta(mb_char_at(text, i - 1))):
            text = text[: i - 1] + text[i : i + 2] + text[i - 1] + text[i + 2 :]

        if (
            i > 1
            and i < len(text) - 1
            and mb_char_at(text, i) == "্"
            and mb_char_at(text, i - 1) == "র"
            and mb_char_at(text, i - 2) != "্"
            and is_bangla_kar(mb_char_at(text, i + 1))
        ):
            text = text[: i - 1] + text[i + 1] + text[i - 1 : i + 1] + text[i + 2 :]

        if i < len(text) - 1 and is_bangla_pre_kar(mb_char_at(text, i)) and not is_space(mb_char_at(text, i + 1)):
            temp = text[:i]
            j = 1
            while i + j < len(text) - 1 and is_bangla_banjonborno(mb_char_at(text, i + j)):
                if i + j < len(text) and is_bangla_halant(mb_char_at(text, i + j + 1)):
                    j += 2
                else:
                    break
            temp += text[i + 1 : i + j + 1]
            l = 0
            if mb_char_at(text, i) == "ে" and mb_char_at(text, i + j + 1) == "া":
                temp += "ো"
                l = 1
            elif mb_char_at(text, i) == "ে" and mb_char_at(text, i + j + 1) == "ৗ":
                temp += "ৌ"
                l = 1
            else:
                temp += mb_char_at(text, i)
            temp += text[i + j + l + 1 :]
            text = temp
            i += j

        if i < len(text) - 1 and is_bangla_nukta(mb_char_at(text, i)) and is_bangla_post_kar(mb_char_at(text, i + 1)):
            text = text[:i] + text[i + 1] + text[i] + text[i + 2 :]

        i += 1

    return text


def convert_bijoy_to_unicode(source: str) -> str:
    text = do_char_map(source, PRE_CONVERSION_MAP)
    text = do_char_map(text, CONVERSION_MAP)
    text = rearrange_unicode_converted_text(text)
    text = do_char_map(text, POST_CONVERSION_MAP)
    return text


def find_pdf_path(root: Path) -> Path:
    for path in root.iterdir():
        if path.suffix.lower() == ".pdf" and "Zone.Identifier" not in path.name:
            return path
    raise FileNotFoundError("No PDF file found")


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    pdf_path = find_pdf_path(root)
    reader = PdfReader(str(pdf_path))

    out_dir = root / "tmp"
    out_dir.mkdir(exist_ok=True)

    converted_pages: list[str] = []
    for index, page in enumerate(reader.pages, start=1):
        raw = page.extract_text() or ""
        converted = convert_bijoy_to_unicode(raw)
        converted_pages.append(f"===== PAGE {index} =====\n{converted.strip()}\n")

    output_path = out_dir / "mosque_pdf_unicode.txt"
    output_path.write_text("\n".join(converted_pages), encoding="utf-8")

    print(f"pdf={pdf_path.name.encode('unicode_escape').decode('ascii')}")
    print(f"pages={len(reader.pages)}")
    print(f"output={output_path}")


if __name__ == "__main__":
    main()
