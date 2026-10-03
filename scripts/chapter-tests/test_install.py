#!/usr/bin/env python3
import contextlib
import importlib.util
import io
import pathlib
import tempfile
import unittest

SOURCE = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('catalog_installer', str(SOURCE / 'scripts/chapter-tests/install.py'))
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)


class TopicMappingInstallerTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name) / 'app'
        self.private = pathlib.Path(self.temp.name) / 'backups'
        self.root.mkdir()
        self.private.mkdir()
        self.saved = installer.ROOT, installer.PRIVATE, installer.run, installer.MANIFEST
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

    def tearDown(self):
        installer.ROOT, installer.PRIVATE, installer.run, installer.MANIFEST = self.saved
        self.temp.cleanup()

    def run_step(self, command, log):
        self.calls.append(command)
        if command[1].endswith('apply.php'):
            log.write_text('CHAPTER_TESTS_COMMITTED\n')

    def apply(self):
        with contextlib.redirect_stdout(io.StringIO()):
            installer.apply()

    def test_unknown_live_edit_is_rejected_before_any_file_is_changed(self):
        path = 'app/Models/Test.php'
        (self.root / path).write_bytes(b'Unexpected live change')
        installer.run = self.run_step

        with self.assertRaisesRegex(RuntimeError, 'Live file requires review'):
            self.apply()

        self.assertEqual(b'Unexpected live change', (self.root / path).read_bytes())
        self.assertFalse((self.root / 'app/Services/ChapterTestGenerator.php').exists())
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
        self.assertEqual('preserve me', (self.root / 'unrelated.txt').read_text())

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


if __name__ == '__main__':
    unittest.main()
