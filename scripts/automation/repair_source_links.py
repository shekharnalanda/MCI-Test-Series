#!/usr/bin/env python3
"""Reuse the guarded installer for the four reviewed source URL corrections only."""
import importlib.util
import json
import pathlib
import sys

SOURCE = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('mci_installer', str(SOURCE / 'scripts/automation/install.py'))
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)
installer.MANIFEST = json.loads((SOURCE / 'scripts/automation/source-link-manifest.json').read_text())
installer.PRIVATE = installer.HOME / 'mci-source-link-backups'
installer.APPLY_SCRIPT = 'scripts/automation/repair-source-links.php'
installer.SUCCESS_MARKER = 'SOURCE_LINKS_COMMITTED'
try:
    installer.install()
except Exception as error:
    print('STOPPED: ' + str(error), flush=True)
    sys.exit(1)
