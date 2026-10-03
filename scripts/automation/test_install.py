#!/usr/bin/env python3
import contextlib
import importlib.util
import io
import pathlib
import tempfile
import unittest
from unittest.mock import patch

SOURCE = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('catalog_installer', str(SOURCE / 'scripts/automation/install.py'))
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)


class AutomationInstallerTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name) / 'app'
        self.private = pathlib.Path(self.temp.name) / 'backups'
        self.root.mkdir()
        self.private.mkdir()
        self.saved = installer.ROOT, installer.PRIVATE, installer.run, installer.MANIFEST, installer.read_cron, installer.write_cron
        installer.MANIFEST = [dict(entry) for entry in installer.MANIFEST]
        self.originals = {}
        for entry in installer.MANIFEST:
            old = ('Previous version of '+entry['path']).encode() if entry['old'] is not None else None
            entry['old'] = installer.checksum(old)
            self.originals[entry['path']] = old
            if old is not None:
                target = self.root / entry['path']
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(old)
        (self.root / 'unrelated.txt').write_text('preserve me')
        installer.ROOT, installer.PRIVATE = self.root, self.private
        self.calls = []
        self.cron = b'23 4 * * * /bin/true # other-site\n' + (installer.OLD_CRON+'\n').encode()
        installer.read_cron = lambda: self.cron
        installer.write_cron = lambda data: setattr(self, 'cron', data)

    def tearDown(self):
        installer.ROOT, installer.PRIVATE, installer.run, installer.MANIFEST, installer.read_cron, installer.write_cron = self.saved
        self.temp.cleanup()

    def run_step(self, command, log):
        self.calls.append(command)
        if command[1].endswith('apply.php'):
            log.write_text('AUTOMATION_COMMITTED\n')

    def apply(self):
        with contextlib.redirect_stdout(io.StringIO()):
            installer.apply()

    def test_unknown_live_edit_is_rejected_before_any_file_is_changed(self):
        path = 'app/Services/AutomaticTestGenerator.php'
        (self.root / path).write_bytes(b'Unexpected live change')
        installer.run = self.run_step

        with self.assertRaisesRegex(RuntimeError, 'Live file requires review'):
            self.apply()

        self.assertEqual(b'Unexpected live change', (self.root / path).read_bytes())
        self.assertFalse((self.root / 'app/Console/Commands/RefreshQuestionBank.php').exists())
        self.assertFalse(any('down' in call for call in self.calls))

    def test_failed_verification_restores_previous_files_and_returns_site_online(self):
        def fail_verify(command, log):
            self.run_step(command, log)
            if command[1].endswith('apply.php'):
                raise RuntimeError('Verification failed')
        installer.run = fail_verify

        with self.assertRaisesRegex(RuntimeError, 'Verification failed'):
            self.apply()

        for path, data in self.originals.items():
            if data is None:
                self.assertFalse((self.root / path).exists())
            else:
                self.assertEqual(data, (self.root / path).read_bytes())
        self.assertIn('up', self.calls[-1])
        self.assertIn(b'# other-site', self.cron)
        self.assertEqual('preserve me', (self.root / 'unrelated.txt').read_text())
        self.assertIn(installer.OLD_CRON.encode(), self.cron)

    def test_success_installs_only_reviewed_files_and_keeps_private_backups(self):
        installer.run = self.run_step

        self.apply()

        for path in self.originals:
            self.assertEqual((SOURCE / path).read_bytes(), (self.root / path).read_bytes())
        self.assertEqual('preserve me', (self.root / 'unrelated.txt').read_text())
        backup = next(p for p in self.private.iterdir() if p.is_dir())
        for path, data in self.originals.items():
            if data is not None:
                self.assertEqual(data, (backup / 'files' / path).read_bytes())
        self.assertIn('up', self.calls[-1])
        self.assertEqual(b'23 4 * * * /bin/true # other-site\n' + (installer.NEW_CRON+'\n').encode(), self.cron)

    def test_an_unreviewed_cron_change_is_rejected_before_any_live_mutation(self):
        self.cron = self.cron.replace(b'*/15', b'*/10')
        installer.run = self.run_step
        with self.assertRaisesRegex(RuntimeError, 'MCI cron changed'):
            self.apply()
        self.assertEqual([], self.calls)
        self.assertFalse((self.root / 'scripts/scheduler-run.sh').exists())

    def test_cpanel_shell_inserts_are_accepted_without_ignoring_job_or_other_env_changes(self):
        canonical = self.cron
        rewritten = b''.join(installer.CPANEL_SHELL + b'\n' + line for line in canonical.splitlines(keepends=True))
        self.assertEqual(canonical, installer.canonical_cron(rewritten))
        self.assertNotEqual(canonical, installer.canonical_cron(rewritten.replace(b'/bin/true', b'/bin/false')))
        self.assertNotEqual(canonical, installer.canonical_cron(rewritten + b'PATH=/unreviewed\n'))

    def test_real_cron_writer_verifies_the_known_server_rewrite_and_preserves_other_jobs(self):
        requested = installer.updated_cron(self.cron)
        rewritten = b''.join(installer.CPANEL_SHELL + b'\n' + line for line in requested.splitlines(keepends=True))
        installer.read_cron = lambda: rewritten
        real_writer = self.saved[-1]
        with patch.object(installer.subprocess, 'run') as call:
            call.return_value.returncode = 0
            real_writer(requested)
            self.assertEqual(requested, call.call_args.kwargs['input'])
        installer.read_cron = lambda: rewritten.replace(b'/bin/true', b'/bin/false')
        with patch.object(installer.subprocess, 'run') as call:
            call.return_value.returncode = 0
            with self.assertRaisesRegex(RuntimeError, 'could not be verified'):
                real_writer(requested)


if __name__ == '__main__':
    unittest.main()
