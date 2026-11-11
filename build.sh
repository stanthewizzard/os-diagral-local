#!/bin/sh
set -eu
cd "$(dirname "$0")"
find src -name '*.php' -exec /usr/local/bin/php -l {} \;
/usr/local/bin/python3 - <<'PY'
import json,pathlib,subprocess,xml.etree.ElementTree as ET
root=pathlib.Path.cwd()
for p in (root/'src').rglob('*.xml'): ET.parse(p)
manifest=json.loads((root/'manifest.json').read_text())
manifest['abi']=subprocess.check_output(['pkg','config','ABI'],text=True).strip()
manifest['deps']={'opnsense':{'origin':'opnsense/opnsense','version':subprocess.check_output(['opnsense-version','-v'],text=True).strip()}}
(root/'manifest.build.json').write_text(json.dumps(manifest))
(root/'plist').write_text('\n'.join('/'+str(p.relative_to(root/'src')) for p in (root/'src').rglob('*') if p.is_file())+'\n')
PY
mkdir -p dist
pkg create -M manifest.build.json -r src -p plist -o dist
