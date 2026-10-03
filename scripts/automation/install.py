#!/usr/bin/env python3
"""Install reviewed automation files and replace only MCI's cron, with private rollback."""
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
PRIVATE = HOME / 'mci-automation-backups'
PHP = '/usr/local/bin/ea-php83'
SOURCE = pathlib.Path(__file__).resolve().parents[2]
MANIFEST = json.loads((SOURCE / 'scripts/automation/manifest.json').read_text())
MARKER = '# MCI_TEST_SERIES_SCHEDULER'
OLD_CRON = '*/15 * * * * cd /home4/mcied45x/repositories/MCI-Test-Series && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /home4/mcied45x/repositories/MCI-Test-Series/storage/logs/scheduler.log 2>&1 ' + MARKER
NEW_CRON = '* * * * * /bin/bash /home4/mcied45x/repositories/MCI-Test-Series/scripts/scheduler-run.sh >> /home4/mcied45x/repositories/MCI-Test-Series/storage/logs/scheduler.log 2>&1 ' + MARKER


def checksum(data):
    return hashlib.sha256(data).hexdigest() if data is not None else None


def run(command, log):
    with log.open('ab') as stream:
        result = subprocess.run(command, cwd=str(ROOT), stdout=stream, stderr=subprocess.STDOUT,
                                timeout=180, env=dict(os.environ, APP_DEBUG='false'))
    if result.returncode:
        raise RuntimeError('Deployment step failed. Private log: ' + str(log))


def read_cron():
    result = subprocess.run(['crontab', '-l'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
    if result.returncode:
        raise RuntimeError('Existing crontab could not be read.')
    return result.stdout


def write_cron(data):
    result = subprocess.run(['crontab', '-'], input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
    if result.returncode or read_cron() != data:
        raise RuntimeError('Crontab update could not be verified.')


def updated_cron(data):
    lines = data.decode('utf-8').splitlines(keepends=True)
    matching = [index for index, line in enumerate(lines) if MARKER in line]
    if len(matching) != 1 or lines[matching[0]].strip() not in (OLD_CRON, NEW_CRON):
        raise RuntimeError('MCI cron changed since review; no entries were modified.')
    lines[matching[0]] = NEW_CRON + '\n'
    return ''.join(lines).encode('utf-8')


def write_file(target, data, mode):
    target.parent.mkdir(parents=True, exist_ok=True)
    fd, name = tempfile.mkstemp(prefix='.automation-', dir=str(target.parent))
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
        raise RuntimeError('MCI application is missing.')
    if (ROOT / 'storage/framework/down').exists():
        raise RuntimeError('Application is already in maintenance mode.')
    if PRIVATE.is_symlink() or ROOT.is_symlink():
        raise RuntimeError('Deployment root cannot be a symlink.')
    os.umask(0o077)
    PRIVATE.mkdir(mode=0o700, exist_ok=True)
    os.chmod(str(PRIVATE), 0o700)
    directory = ROOT / 'storage/app/automation'
    if any(parent.is_symlink() for parent in [directory] + list(directory.parents)):
        raise RuntimeError('Scheduler lock path cannot be a symlink.')
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    if (directory / 'scheduler.lock').is_symlink():
        raise RuntimeError('Scheduler lock cannot be a symlink.')
    with (PRIVATE / 'install.lock').open('a') as deployment, (directory / 'scheduler.lock').open('a') as scheduler:
        fcntl.flock(deployment, fcntl.LOCK_EX | fcntl.LOCK_NB)
        fcntl.flock(scheduler, fcntl.LOCK_EX | fcntl.LOCK_NB)
        apply()


def apply():
    backup = pathlib.Path(tempfile.mkdtemp(prefix=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S-'), dir=str(PRIVATE)))
    log = backup / 'install.log'
    old_cron = read_cron()
    new_cron = updated_cron(old_cron)
    (backup / 'cron-before.txt').write_bytes(old_cron)
    staged = []
    for entry in MANIFEST:
        relative = pathlib.PurePosixPath(entry['path'])
        if relative.is_absolute() or '..' in relative.parts:
            raise RuntimeError('Invalid deployment path.')
        target = ROOT / entry['path']
        if target.is_symlink() or any(parent.is_symlink() for parent in target.parents):
            raise RuntimeError('Deployment path cannot be a symlink.')
        data = (SOURCE / entry['path']).read_bytes()
        current = target.read_bytes() if target.exists() else None
        if checksum(data) != entry['new']:
            raise RuntimeError('Staged checksum mismatch: ' + entry['path'])
        if checksum(current) not in (entry['old'], entry['new']):
            raise RuntimeError('Live file requires review: ' + entry['path'])
        mode = target.stat().st_mode & 0o777 if target.exists() else 0o644
        staged.append((target, data, current, mode))
        if current is not None:
            saved = backup / 'files' / entry['path']
            saved.parent.mkdir(parents=True, exist_ok=True)
            saved.write_bytes(current)
            if saved.read_bytes() != current:
                raise RuntimeError('Private backup failed.')
        if entry['path'].endswith('.php'):
            run([PHP, '-l', str(SOURCE / entry['path'])], log)
    run([PHP, '-l', str(SOURCE / 'scripts/automation/apply.php')], log)
    run(['/bin/bash', '-n', str(SOURCE / 'scripts/scheduler-run.sh')], log)
    if read_cron() != old_cron:
        raise RuntimeError('Crontab changed during review.')
    (backup / 'manifest.json').write_text(json.dumps(MANIFEST))
    print('REVIEWED | private backups ready | exact file/cron checksums | only MCI changes', flush=True)
    written = []
    maintenance = False
    cron_written = False
    try:
        run([PHP, 'artisan', 'down', '--retry=30', '--no-interaction'], log)
        maintenance = True
        for target, data, current, mode in staged:
            write_file(target, data, mode)
            written.append((target, current, mode))
        run([PHP, 'artisan', 'list', '--raw', '--no-interaction'], log)
        if read_cron() != old_cron:
            raise RuntimeError('Crontab changed before installation.')
        cron_written = True
        write_cron(new_cron)
        run([PHP, str(SOURCE / 'scripts/automation/apply.php'), str(ROOT), str(backup)], log)
        if 'AUTOMATION_COMMITTED' not in log.read_text():
            raise RuntimeError('Verification did not confirm the transaction.')
        for line in log.read_text().splitlines():
            if line.startswith(('VERIFIED |', 'AUTOMATION_COMMITTED')):
                print(line, flush=True)
        run([PHP, 'artisan', 'up', '--no-interaction'], log)
        maintenance = False
        print('APPLIED | MCI cron every minute | flock + nice + 240s timeout | other cron entries preserved', flush=True)
        print('PRIVATE_BACKUP=' + str(backup), flush=True)
    except Exception:
        if cron_written:
            # Never overwrite a concurrent crontab edit.
            current_cron = read_cron()
            if current_cron == new_cron:
                write_cron(old_cron)
            elif current_cron != old_cron:
                print('STOPPED | concurrent cron edit needs review; private backup retained', flush=True)
        for target, current, mode in reversed(written):
            if current is None:
                if target.exists():
                    target.unlink()
            else:
                write_file(target, current, mode)
        print('STOPPED | reviewed code/cron restored | verified added content may remain | private log: ' + str(log), flush=True)
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
