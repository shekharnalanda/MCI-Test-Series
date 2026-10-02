#!/usr/bin/env python3
"""Apply only the reviewed catalog files, with private backups and rollback."""
import datetime
import fcntl
import hashlib
import json
import os
import pathlib
import subprocess
import sys
import tempfile

HOME = pathlib.Path('/home4/mcied45x')
ROOT = HOME / 'repositories/MCI-Test-Series'
PRIVATE = HOME / 'mci-test-catalog-backups'
PHP = '/usr/local/bin/ea-php83'
SOURCE = pathlib.Path(__file__).resolve().parents[2]
MANIFEST = json.loads((SOURCE / 'scripts/test-catalog/manifest.json').read_text())


def checksum(data):
    return hashlib.sha256(data).hexdigest() if data is not None else None


def run(command, log):
    with log.open('ab') as stream:
        result = subprocess.run(command, cwd=str(ROOT), stdout=stream, stderr=subprocess.STDOUT,
                                timeout=180, env=dict(os.environ, APP_DEBUG='false'))
    if result.returncode:
        raise RuntimeError('Deployment step failed. Private log: ' + str(log))


def write_file(target, data, mode):
    target.parent.mkdir(parents=True, exist_ok=True)
    fd, name = tempfile.mkstemp(prefix='.catalog-', dir=str(target.parent))
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
        os.chmod(name, mode)
        os.replace(name, str(target))
    finally:
        if os.path.exists(name):
            os.unlink(name)


def install():
    if pathlib.Path.home() != HOME or os.getuid() == 0:
        raise RuntimeError('Use the mcied45x cPanel Terminal.')
    if not (ROOT / 'artisan').is_file() or not (ROOT / '.env').is_file():
        raise RuntimeError('MCI Test Series application root is missing.')
    if (ROOT / 'storage/framework/down').exists():
        raise RuntimeError('The application is already in maintenance mode.')
    if PRIVATE.is_symlink() or ROOT.is_symlink():
        raise RuntimeError('Deployment root/backup cannot be a symlink.')
    os.umask(0o077)
    PRIVATE.mkdir(mode=0o700, exist_ok=True)
    os.chmod(str(PRIVATE), 0o700)
    with (PRIVATE / 'install.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        apply()


def apply():
    backup = pathlib.Path(tempfile.mkdtemp(prefix=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S-'), dir=str(PRIVATE)))
    log = backup / 'install.log'
    staged = []
    for entry in MANIFEST:
        relative = pathlib.PurePosixPath(entry['path'])
        if relative.is_absolute() or '..' in relative.parts:
            raise RuntimeError('Invalid deployment path.')
        target = ROOT / entry['path']
        if target.is_symlink() or any(p.is_symlink() for p in target.parents):
            raise RuntimeError('Deployment path cannot be a symlink.')
        data = (SOURCE / entry['path']).read_bytes()
        current = target.read_bytes() if target.exists() else None
        if checksum(data) != entry['new']:
            raise RuntimeError('Staged checksum mismatch: ' + entry['path'])
        if checksum(current) not in [entry['old'], entry['new']] + entry.get('previous_versions', []):
            raise RuntimeError('Live file requires review: ' + entry['path'])
        mode = target.stat().st_mode & 0o777 if target.exists() else 0o644
        staged.append((target, data, current, mode))
        if current is not None:
            saved = backup / 'files' / entry['path']
            saved.parent.mkdir(parents=True, exist_ok=True)
            saved.write_bytes(current)
            if saved.read_bytes() != current:
                raise RuntimeError('Private backup failed.')
        if entry['path'].endswith('.php') and not entry['path'].endswith('.blade.php'):
            run([PHP, '-l', str(SOURCE / entry['path'])], log)
    run([PHP, '-l', str(SOURCE / 'scripts/test-catalog/verify.php')], log)
    (backup / 'manifest.json').write_text(json.dumps(MANIFEST))
    print('REVIEWED | exact live checksums | private backups ready | six catalog files only', flush=True)
    written = []
    maintenance = False
    try:
        run([PHP, 'artisan', 'down', '--retry=30', '--no-interaction'], log)
        maintenance = True
        for target, data, current, mode in staged:
            write_file(target, data, mode)
            written.append((target, current, mode))
        run([PHP, 'artisan', 'view:clear', '--no-interaction'], log)
        run([PHP, 'artisan', 'view:cache', '--no-interaction'], log)
        run([PHP, str(SOURCE / 'scripts/test-catalog/verify.php'), str(ROOT)], log)
        if 'CATALOG_LIVE_CHECK_OK' not in log.read_text():
            raise RuntimeError('Live verification did not confirm success.')
        for line in log.read_text().splitlines():
            if line.startswith('VERIFIED |') or line.startswith('CATALOG_LIVE_CHECK_OK'):
                print(line, flush=True)
        run([PHP, 'artisan', 'up', '--no-interaction'], log)
        maintenance = False
        print('APPLIED | category -> exam -> subject -> chapter | paid and library panels', flush=True)
        print('PRIVATE_BACKUP=' + str(backup), flush=True)
    except Exception:
        for target, current, mode in reversed(written):
            if current is None:
                if target.exists():
                    target.unlink()
            else:
                write_file(target, current, mode)
        try:
            run([PHP, 'artisan', 'view:clear', '--no-interaction'], log)
        except Exception:
            pass
        print('STOPPED | reviewed catalog files restored | private log: ' + str(log), flush=True)
        raise
    finally:
        if maintenance:
            run([PHP, 'artisan', 'up', '--no-interaction'], log)


if __name__ == '__main__':
    try:
        install()
    except Exception as error:
        print('STOPPED: ' + str(error), flush=True)
        sys.exit(1)
