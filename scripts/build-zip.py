"""Build a runtime-only WordPress release ZIP, including the licensed update checker."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re
root = Path(__file__).resolve().parents[1]
main = 'websitebeheer.php'
version = re.search(r"Version:\s*([0-9.]+)", (root / main).read_text()).group(1)
files = [root / name for name in ['websitebeheer.php', 'README.md', 'CHANGELOG.md']]
for folder in ('includes', 'assets'):
    files.extend(p for p in (root / folder).rglob('*') if p.is_file())
assert all(not p.is_symlink() for p in files)
assert all(p.suffix in ('.php', '.json', '.css', '.js', '.md', '.txt', '.mo', '.po', '.pot', '.svg') for p in files)
output = root / 'dist' / ('emonks-dashboard-' + version + '.zip')
output.parent.mkdir(exist_ok=True)
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for file in sorted(files):
        archive.write(file, 'websitebeheer/' + str(file.relative_to(root)))
with ZipFile(output) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(files)
    assert 'websitebeheer/includes/plugin-update-checker/license.txt' in archive.namelist()
print(str(output) + ' (' + str(len(files)) + ' files)')
