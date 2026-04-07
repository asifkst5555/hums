
import json
import re
from pathlib import Path

SOURCE = Path('tmp/mosque_pdf_rows.json')
TARGET = Path('tmp/mosque_pdf_rows_clean.json')
CSV_TARGET = Path('tmp/mosque_pdf_rows_clean.csv')

BN_TO_ASCII = str.maketrans('??????????', '0123456789')


def norm_space(value: str) -> str:
    value = (value or '').replace('\ufeff', ' ').replace('\xa0', ' ').replace('\xff', '')
    value = value.replace('?', ' ')
    value = re.sub(r'\s+', ' ', value)
    return value.strip(' ,;:-')


def dedupe_phrase(value: str) -> str:
    value = norm_space(value)
    parts = value.split()
    if len(parts) % 2 == 0 and parts[: len(parts) // 2] == parts[len(parts) // 2 :]:
        return ' '.join(parts[: len(parts) // 2])
    return value


def is_header(row: dict, index: int) -> bool:
    if index == 0:
        return True
    blob = ' '.join(norm_space(str(row.get(key, ''))) for key in row)
    markers = [
        '??????? ?????????',
        '??????/ ?????? ??????? ??????? ????? ??????',
        '??????? ??? ? ??????? ???',
        '???? ????? ??????????',
        '?????? ??? ? ??????',
        '??????????? ??? ? ??????',
        '???????',
    ]
    return any(marker in blob for marker in markers)


def normalize_phone(value: str) -> str:
    value = norm_space(value)
    if not value:
        return ''
    chunks = []
    for match in re.findall(r'[0-9?-?]{6,}', value):
        chunks.append(match.translate(BN_TO_ASCII))
    return ', '.join(dict.fromkeys(chunks))


def strip_labels(value: str) -> str:
    value = norm_space(value)
    labels = [
        '?????? ??? ? ?????? ??',
        '?????? ??? ? ?????? ??',
        '??????????? ??? ? ?????? ??',
        '?????? ??? ? ??????',
        '?????? ??? ? ??????',
        '??????????? ??? ? ??????',
    ]
    for label in labels:
        if value.startswith(label):
            value = norm_space(value[len(label):])
    value = re.sub(r'^[?-?0-9]+\s*', '', value)
    return value


def split_person(raw_name: str, raw_phone: str) -> tuple[str, str]:
    name = strip_labels(raw_name)
    phone = normalize_phone(raw_phone)
    if not phone and name:
        matches = re.findall(r'[0-9?-?]{8,}', name)
        if matches:
            phone = ', '.join(dict.fromkeys(match.translate(BN_TO_ASCII) for match in matches))
            for match in matches:
                name = name.replace(match, ' ')
            name = norm_space(name)
    if name in {'?', '?', '?'}:
        name = ''
    return name, phone


def normalize_union_and_ward(raw_union: str, raw_ward: str, last_union: str, last_ward: str) -> tuple[str, str, str, str]:
    union = dedupe_phrase(raw_union)
    ward = norm_space(raw_ward)

    if ward in {'??????', '????', '??? ????', '??? ????', '??????'}:
        ward = ''

    if not union:
        union = last_union
    if not ward:
        ward = last_ward

    municipality_match = re.match(r'^(.*???????)[, ]+([?-?0-9]+)\s*???$', union)
    if municipality_match:
        union = norm_space(municipality_match.group(1))
        if not ward:
            ward = municipality_match.group(2) + '?? ??????'

    generic_ward_match = re.match(r'^(.*?)([?-?0-9]+)\s*??$', union)
    if generic_ward_match and raw_ward.strip() in {'??????', '????'} and '??????' not in union and '??????' not in union:
        union = norm_space(generic_ward_match.group(1))
        ward = generic_ward_match.group(2) + '?? ??????'

    ward = ward.replace('?? ward', '?? ??????')
    if ward and '??????' not in ward and re.search(r'[?-?0-9]', ward):
        ward = ward + ' ??????'
    ward = norm_space(ward)
    union = dedupe_phrase(union)

    return union, ward, union or last_union, ward or last_ward


def split_name_addr(value: str) -> tuple[str, str]:
    value = norm_space(value)
    if ',' in value:
        name, addr = value.split(',', 1)
        return norm_space(name), norm_space(addr)
    return value, ''


def normalize_yes_no(flag: str, madrasa_name: str) -> str:
    flag = norm_space(flag)
    madrasa_name = norm_space(madrasa_name)
    if '?????' in flag:
        return 'yes'
    if '??' in flag:
        return 'no'
    if madrasa_name and madrasa_name != '?':
        return 'yes'
    return 'no'


rows = json.loads(SOURCE.read_text(encoding='utf-8'))
clean_rows = []
last_union = ''
last_ward = ''

for index, row in enumerate(rows):
    if is_header(row, index):
        continue

    union, ward, last_union, last_ward = normalize_union_and_ward(
        str(row.get('union', '')),
        str(row.get('ward', '')),
        last_union,
        last_ward,
    )

    name, addr = split_name_addr(str(row.get('mosque_name_address', '')))
    khatib_name, khatib_phone = split_person(str(row.get('khatib_name', '')), str(row.get('khatib_phone', '')))
    imam_name, imam_phone = split_person(str(row.get('imam_name', '')), str(row.get('imam_phone', '')))
    muazzin_name, muazzin_phone = split_person(str(row.get('muazzin_name', '')), str(row.get('muazzin_phone', '')))
    madrasa_name = norm_space(str(row.get('madrasa_name', '')))
    if madrasa_name == '?':
        madrasa_name = ''
    madrasa_present = normalize_yes_no(str(row.get('madrasa_present', '')), madrasa_name)

    clean = {
        'serial': norm_space(str(row.get('serial', ''))),
        'union': union,
        'wardNo': ward,
        'name': name,
        'addr': addr,
        'mosqueType': norm_space(str(row.get('mosque_type', ''))),
        'khatibName': khatib_name,
        'khatibPhone': khatib_phone,
        'imamName': imam_name,
        'imamPhone': imam_phone,
        'muazzinName': muazzin_name,
        'muazzinPhone': muazzin_phone,
        'madrasaPresent': madrasa_present,
        'madrasaName': madrasa_name,
    }

    if not clean['name']:
        continue
    clean_rows.append(clean)

TARGET.write_text(json.dumps(clean_rows, ensure_ascii=False, indent=2), encoding='utf-8')

headers = [
    'serial', 'union', 'wardNo', 'name', 'addr', 'mosqueType',
    'khatibName', 'khatibPhone', 'imamName', 'imamPhone',
    'muazzinName', 'muazzinPhone', 'madrasaPresent', 'madrasaName'
]
with CSV_TARGET.open('w', encoding='utf-8-sig', newline='') as fh:
    import csv
    writer = csv.DictWriter(fh, fieldnames=headers)
    writer.writeheader()
    writer.writerows(clean_rows)

print(f'clean_rows={len(clean_rows)}')
print('sample_union=' + clean_rows[0]['union'].encode('unicode_escape').decode('ascii'))
print('sample_name=' + clean_rows[0]['name'].encode('unicode_escape').decode('ascii'))
