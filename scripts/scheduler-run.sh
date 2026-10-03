#!/usr/bin/env bash
# One bounded, low-priority scheduler per MCI application; never background jobs.
set -eu
umask 077
mci_root="$(cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$mci_root"
mci_php=/usr/local/bin/ea-php83
if [[ ! -x "$mci_php" ]]; then
    echo 'MCI scheduler: production PHP 8.3 is unavailable.' >&2
    exit 1
fi
mkdir -p storage/app/automation
exec 9>storage/app/automation/scheduler.lock
/bin/flock -n 9 || exit 0
# timeout owns its process group so a stuck job and its children are stopped.
# Event overlap locks expire separately; this is the actual runtime bound.
set +e
/bin/nice -n 10 /bin/timeout --signal=TERM --kill-after=10s 240s "$mci_php" artisan schedule:run
mci_status=$?
set -e
if [[ "$mci_status" -eq 124 || "$mci_status" -eq 137 ]]; then
    echo "$(date -u +%FT%TZ) MCI_SCHEDULER_TIMEOUT limit=240s" >&2
fi
exit "$mci_status"
