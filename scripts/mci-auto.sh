#!/bin/bash

set -u
set -o pipefail

APP="/home4/mcied45x/repositories/MCI-Test-Series"
STATE="$APP/storage/app/automation/state"
LOG="$APP/storage/logs/automation/auto.log"
LOCK="$APP/storage/app/automation/runner.lock"

cd "$APP" || exit 1

mkdir -p \
    "$APP/storage/app/automation" \
    "$APP/storage/logs/automation"

touch "$LOG"

exec >>"$LOG" 2>&1

echo
echo "=================================================="
echo "MCI AUTO RUN: $(date)"
echo "=================================================="

if ! mkdir "$LOCK" 2>/dev/null; then
    echo "Another runner is already active."
    exit 0
fi

cleanup() {
    rmdir "$LOCK" 2>/dev/null || true
}

trap cleanup EXIT INT TERM HUP

STEP="$(cat "$STATE" 2>/dev/null || echo 0)"

save_step() {
    echo "$1" > "$STATE"
    STEP="$1"
}

php_audit() {

    echo "Scanning PHP files..."

    COUNT=0

    while IFS= read -r -d '' FILE
    do
        COUNT=$((COUNT + 1))

        echo "PHP[$COUNT]: $FILE"

        if command -v timeout >/dev/null 2>&1; then
            timeout 20 php -l "$FILE" >/dev/null
            RC=$?
        else
            php -l "$FILE" >/dev/null
            RC=$?
        fi

        if [ "$RC" -ne 0 ]; then
            echo "PHP AUDIT FAILED:"
            echo "$FILE"
            echo "Exit code: $RC"
            return 1
        fi

    done < <(
        find app database routes tests bootstrap \
        -type f -name '*.php' -print0
    )

    echo "PHP files checked: $COUNT"
    return 0
}

command_audit() {

    php artisan list > /tmp/mci-artisan-list.txt 2>&1 \
        || return 1

    grep -q "mci:question-bank-plan" \
        /tmp/mci-artisan-list.txt || return 1

    grep -q "mci:question-bank-import" \
        /tmp/mci-artisan-list.txt || return 1

    grep -q "mci:question-bank-stats" \
        /tmp/mci-artisan-list.txt || return 1

    grep -q "mci:current-affairs-maintain" \
        /tmp/mci-artisan-list.txt || return 1

    return 0
}

test_suite() {

    if command -v timeout >/dev/null 2>&1; then

        timeout 300 env \
            DB_CONNECTION=sqlite \
            DB_DATABASE=:memory: \
            php artisan test

        return $?
    fi

    env \
        DB_CONNECTION=sqlite \
        DB_DATABASE=:memory: \
        php artisan test
}

run_step() {

    NUMBER="$1"
    NAME="$2"
    FUNCTION="$3"

    if [ "$STEP" -ge "$NUMBER" ]; then
        echo "SKIP [$NUMBER] $NAME"
        return 0
    fi

    echo
    echo "START [$NUMBER] $NAME"
    echo "TIME: $(date)"

    "$FUNCTION"
    RC=$?

    if [ "$RC" -eq 0 ]; then
        save_step "$NUMBER"
        echo "PASS [$NUMBER] $NAME"
        return 0
    fi

    echo "FAIL [$NUMBER] $NAME"
    echo "Exit code: $RC"
    echo "Automation stopped at checkpoint $STEP"
    return "$RC"
}

repo_check() {

    git status --short

    git rev-parse --verify HEAD >/dev/null
}

final_check() {

    echo "HEAD:"
    git log --oneline -1

    echo "STATUS:"
    git status --short

    return 0
}

run_step 1 \
    "Repository integrity" \
    repo_check || exit $?

run_step 2 \
    "PHP syntax audit" \
    php_audit || exit $?

run_step 3 \
    "Command registration" \
    command_audit || exit $?

run_step 4 \
    "Automated application tests" \
    test_suite || exit $?

run_step 5 \
    "Final repository check" \
    final_check || exit $?

echo
echo "=================================================="
echo "MCI AUTO RUN COMPLETE"
echo "Checkpoint: $STEP"
echo "Finished: $(date)"
echo "=================================================="
