#!/usr/bin/env python3
"""
Construit les fichiers de traduction à partir de languages/optimizr-fr_FR.po (et de tout autre .po du dossier) :
- optimizr-<locale>.mo : chaînes PHP (load_plugin_textdomain) ;
- optimizr-<locale>-<md5>.json : chaînes de chaque script JS (wp_set_script_translations), une par fichier ;
- optimizr.pot : modèle pour de nouvelles langues.

Usage : python3 bin/build-translations.py  (sans dépendance, Python 3.6+)
À relancer après chaque modification d'un .po.
"""
import hashlib
import json
import os
import re
import struct
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG_DIR = os.path.join(ROOT, 'languages')
DOMAIN = 'optimizr'


def unquote(s):
    s = s.strip()
    assert s.startswith('"') and s.endswith('"'), s
    return (s[1:-1].replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"').replace('\\\\', '\\'))


def parse_po(path):
    """Renvoie (en-têtes, entrées) ; chaque entrée : dict ctx, id, plural, strs (liste), refs, comments."""
    entries = []
    cur = None
    field = None

    def flush():
        if cur is not None and 'id' in cur:
            entries.append(cur)

    with open(path, encoding='utf-8') as f:
        for raw in f:
            line = raw.rstrip('\n')
            if not line.strip():
                flush()
                cur, field = None, None
                continue
            if cur is None:
                cur = {'ctx': None, 'plural': None, 'strs': {}, 'refs': [], 'comments': []}
            if line.startswith('#:'):
                cur['refs'] += line[2:].split()
            elif line.startswith('#.'):
                cur['comments'].append(line[2:].strip())
            elif line.startswith('#'):
                continue
            elif line.startswith('msgctxt '):
                cur['ctx'], field = unquote(line[8:]), 'ctx'
            elif line.startswith('msgid_plural '):
                cur['plural'], field = unquote(line[13:]), 'plural'
            elif line.startswith('msgid '):
                cur['id'], field = unquote(line[6:]), 'id'
            elif line.startswith('msgstr['):
                n = int(line[7:line.index(']')])
                cur['strs'][n] = unquote(line[line.index(']') + 1:])
                field = ('str', n)
            elif line.startswith('msgstr '):
                cur['strs'][0], field = unquote(line[7:]), ('str', 0)
            elif line.startswith('"'):
                if isinstance(field, tuple):
                    cur['strs'][field[1]] += unquote(line)
                else:
                    cur[field] += unquote(line)
    flush()

    headers = {}
    body = []
    header_raw = ''
    for e in entries:
        if e['id'] == '' and e['ctx'] is None:
            for h in e['strs'].get(0, '').split('\n'):
                if ':' in h:
                    k, v = h.split(':', 1)
                    headers[k.strip()] = v.strip()
            header_raw = e['strs'].get(0, '')
        else:
            body.append(e)
    headers['_raw'] = header_raw
    return headers, body


def key_of(e):
    k = e['id'] if e['ctx'] is None else e['ctx'] + '\x04' + e['id']
    if e['plural'] is not None:
        k += '\x00' + e['plural']
    return k


def value_of(e):
    n = max(e['strs'].keys()) + 1 if e['strs'] else 1
    return '\x00'.join(e['strs'].get(i, '') for i in range(n))


def write_mo(path, headers, entries):
    pairs = {'': headers['_raw']}
    for e in entries:
        if any(e['strs'].values()):
            pairs[key_of(e)] = value_of(e)
    keys = sorted(pairs)
    ids = b''
    strs = b''
    offsets = []
    for k in keys:
        kb, vb = k.encode('utf-8'), pairs[k].encode('utf-8')
        offsets.append((len(ids), len(kb), len(strs), len(vb)))
        ids += kb + b'\x00'
        strs += vb + b'\x00'
    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + len(ids)
    koffsets, voffsets = [], []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    out = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    out += struct.pack('%di' % len(koffsets), *koffsets)
    out += struct.pack('%di' % len(voffsets), *voffsets)
    out += ids + strs
    with open(path, 'wb') as f:
        f.write(out)


def write_js(locale, headers, entries):
    by_file = {}
    for e in entries:
        if not any(e['strs'].values()):
            continue
        for ref in e['refs']:
            path = ref.split(':')[0]
            if path.endswith('.js'):
                by_file.setdefault(path, []).append(e)
    written = []
    for path, items in sorted(by_file.items()):
        messages = {'': {'domain': 'messages', 'lang': locale,
                         'plural-forms': headers.get('Plural-Forms', 'nplurals=2; plural=(n > 1);')}}
        for e in items:
            k = e['id'] if e['ctx'] is None else e['ctx'] + '\x04' + e['id']
            n = max(e['strs'].keys()) + 1
            messages[k] = [e['strs'].get(i, '') for i in range(n)]
        data = {'translation-revision-date': headers.get('PO-Revision-Date', ''),
                'generator': 'bin/build-translations.py', 'domain': 'messages',
                'locale_data': {'messages': messages}}
        name = '%s-%s-%s.json' % (DOMAIN, locale, hashlib.md5(path.encode('utf-8')).hexdigest())
        with open(os.path.join(LANG_DIR, name), 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, separators=(',', ':'))
        written.append(name)
    return written


def quote(s):
    s = s.replace('\\', '\\\\').replace('"', '\\"').replace('\t', '\\t')
    if '\n' in s:
        parts = s.split('\n')
        lines = [p + '\\n' for p in parts[:-1]] + ([parts[-1]] if parts[-1] else [])
        return '""\n' + '\n'.join('"%s"' % l for l in lines)
    return '"%s"' % s


def write_pot(path, version, entries):
    seen = {}
    for e in entries:
        seen.setdefault(key_of(e), e)
    out = ['# Copyright (C) Haptic', '# This file is distributed under the GPLv2 or later.',
           'msgid ""', 'msgstr ""',
           '"Project-Id-Version: Haptic Optimizr %s\\n"' % version,
           '"MIME-Version: 1.0\\n"', '"Content-Type: text/plain; charset=UTF-8\\n"',
           '"Content-Transfer-Encoding: 8bit\\n"', '"X-Domain: %s\\n"' % DOMAIN, '']
    for e in seen.values():
        out += ['#. ' + c for c in e['comments']]
        if e['refs']:
            out.append('#: ' + ' '.join(e['refs']))
        if e['ctx'] is not None:
            out.append('msgctxt ' + quote(e['ctx']))
        out.append('msgid ' + quote(e['id']))
        if e['plural'] is not None:
            out += ['msgid_plural ' + quote(e['plural']), 'msgstr[0] ""', 'msgstr[1] ""']
        else:
            out.append('msgstr ""')
        out.append('')
    with open(path, 'w', encoding='utf-8') as f:
        f.write('\n'.join(out))


def main():
    version = re.search(r'^ \* Version: *(\S+)', open(os.path.join(ROOT, 'optimizr.php'), encoding='utf-8').read(), re.M).group(1)
    for old in os.listdir(LANG_DIR):
        if old.endswith('.json') or old.endswith('.mo'):
            os.remove(os.path.join(LANG_DIR, old))
    pot_entries = None
    for name in sorted(os.listdir(LANG_DIR)):
        m = re.match(r'^%s-([a-z]{2,3}(?:_[A-Z]{2})?)\.po$' % DOMAIN, name)
        if not m:
            continue
        locale = m.group(1)
        headers, entries = parse_po(os.path.join(LANG_DIR, name))
        write_mo(os.path.join(LANG_DIR, '%s-%s.mo' % (DOMAIN, locale)), headers, entries)
        js = write_js(locale, headers, entries)
        print('%s : %d chaînes, %d fichiers JSON' % (locale, len(entries), len(js)))
        if pot_entries is None:
            pot_entries = entries
    if pot_entries is not None:
        write_pot(os.path.join(LANG_DIR, DOMAIN + '.pot'), version, pot_entries)


if __name__ == '__main__':
    sys.exit(main())
