import os
import pathlib
import subprocess
import tempfile
import time
import unittest

SOURCE = pathlib.Path(__file__).resolve().parents[2]


class BoundedSchedulerTest(unittest.TestCase):
    def fixture(self, body, limit='240s'):
        temporary = tempfile.TemporaryDirectory()
        root = pathlib.Path(temporary.name)
        (root / 'scripts').mkdir()
        php = root / 'php'
        php.write_text('#!/bin/bash\n' + body + '\n')
        php.chmod(0o700)
        script = (SOURCE / 'scripts/scheduler-run.sh').read_text().replace('/usr/local/bin/ea-php83', str(php)).replace('240s "$mci_php"', limit+' "$mci_php"')
        (root / 'scripts/scheduler-run.sh').write_text(script)
        return temporary, root

    def test_an_overlapping_scheduler_exits_without_starting_another_job(self):
        temporary, root = self.fixture('echo started >> starts; touch running; sleep 0.8')
        with temporary:
            first = subprocess.Popen(['/bin/bash', 'scripts/scheduler-run.sh'], cwd=root)
            try:
                deadline = time.monotonic() + 5
                while not (root / 'running').exists() and time.monotonic() < deadline:
                    time.sleep(0.02)
                self.assertTrue((root / 'running').exists())
                second = subprocess.run(['/bin/bash', 'scripts/scheduler-run.sh'], cwd=root, timeout=3)
                self.assertEqual(0, second.returncode)
                self.assertEqual(0, first.wait(timeout=3))
                self.assertEqual(['started'], (root / 'starts').read_text().splitlines())
            finally:
                if first.poll() is None:
                    first.terminate()
                    first.wait(timeout=3)

    def test_a_stuck_job_is_stopped_and_the_global_lock_is_released(self):
        temporary, root = self.fixture('sleep 20', '1s')
        with temporary:
            result = subprocess.run(['/bin/bash', 'scripts/scheduler-run.sh'], cwd=root, capture_output=True, timeout=4)
            self.assertEqual(124, result.returncode)
            self.assertIn(b'MCI_SCHEDULER_TIMEOUT', result.stderr)
            lock = subprocess.run(['/bin/flock', '-n', str(root / 'storage/app/automation/scheduler.lock'), '/bin/true'], timeout=2)
            self.assertEqual(0, lock.returncode)


if __name__ == '__main__':
    unittest.main()
