#!/bin/bash

set -u

APP="/home4/mcied45x/repositories/MCI-Test-Series"
STATE="$APP/storage/app/automation/test-state"
LOG="$APP/storage/logs/automation/tests-safe.log"

cd "$APP" || exit 1
mkdir -p "$(dirname "$STATE")" "$(dirname "$LOG")"

exec >>"$LOG" 2>&1

STEP="$(cat "$STATE" 2>/dev/null || echo 0)"

run_test() {
    NUMBER="$1"
    FILE="$2"

    if [ "$STEP" -ge "$NUMBER" ]; then
        echo "SKIP [$NUMBER] $FILE"
        return 0
    fi

    echo
    echo "========================================"
    echo "START [$NUMBER] $FILE"
    echo "TIME: $(date)"
    echo "========================================"

    env \
        DB_CONNECTION=sqlite \
        DB_DATABASE=:memory: \
        php artisan test "$FILE"

    RC=$?

    if [ "$RC" -ne 0 ]; then
        echo "FAIL [$NUMBER] $FILE"
        echo "Exit=$RC"
        exit "$RC"
    fi

    echo "$NUMBER" > "$STATE"
    STEP="$NUMBER"

    echo "PASS [$NUMBER] $FILE"

    # Give shared hosting a small recovery interval.
    sleep 3
}

run_test 1 tests/Unit/ExampleTest.php
run_test 2 tests/Feature/ExampleTest.php
run_test 3 tests/Feature/ExamEngineTest.php
run_test 4 tests/Feature/QuestionAutomationTest.php
run_test 5 tests/Feature/CurrentAffairsAutomationTest.php
run_test 6 tests/Feature/LargeQuestionBankTest.php
run_test 7 tests/Feature/QuestionIngestionTrustedSourceGateTest.php
run_test 8 tests/Feature/TrustedSourceQuarantineTest.php
run_test 9 tests/Feature/BulkQuestionImportSourceRevalidationTest.php

echo
echo "========================================"
echo "SAFE TEST SUITE COMPLETE"
echo "Checkpoint=$STEP"
echo "Finished=$(date)"
echo "========================================"
