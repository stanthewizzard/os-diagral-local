"""Bounded routing repair only; never sends an alarm command."""
import json
from pathlib import Path
import subprocess
import time

while True:
    try:
        run = subprocess.run(
            ['/usr/local/bin/php', '/usr/local/opnsense/scripts/diagrallocal/guard.php'],
            capture_output=True, timeout=25, text=True,
        )
        if run.returncode == 0:
            result = json.loads(run.stdout)
            Path('/var/run/diagrallocal-guard.json').write_text(json.dumps(result))
    except (OSError, ValueError, subprocess.TimeoutExpired):
        pass
    time.sleep(10)
