# Bounded question-bank and test automation

Production entry point is `scripts/scheduler-run.sh`, invoked by cron every minute. The wrapper holds one application-wide `flock`, uses nice priority 10, and stops its process group after 240 seconds (TERM then KILL after 10 seconds). It does not start background jobs. Laravel still determines when actual work runs.

| Task | IST minute | Work bound |
| --- | --- | --- |
| Practice sets | Every hour at :00 | 3 per run; 10 per exam/month |
| Full mocks | Every hour at :05 | 1 per run; 5 per exam/month |
| Official feed fetch | Every hour at :10 | 30 items per source |
| Reviewed SSC/Bihar Police chapter sets | Every hour at :20 | 3 per run; 2 per topic/exam/month |
| Verified current affairs questions | Every hour at :25 | 10 per run |
| Question bank refresh | Every hour at :40 | One family/page, at most 50 source rows |

Question refresh rotates 15 country fact families, discoveries, software and books, saving an independent page offset for each. It excludes multi-valued facts in the source query before pagination, requires complete Hindi/English facts and four distinct answers, and uses the existing trusted-source/ingestion checks. New legislature and single-time-zone templates provide additional Static GK facts. Short pages reset that family; HTTP/invalid-result failures retain the cursor. Reimports deduplicate without overwriting old questions or explanations. Other monthly imports are reduced to 50 rows. The existing manual import commands remain available.

Monthly generation is opt-in on commands; legacy lifetime behavior remains the default. It appends tests/series and question pivots without editing old papers or attempts. Selection rotates exams by their oldest generation date and rejects identical question-ID sets. Monthly limits are checked under an exam-row lock. Student monthly quota rules are unchanged. A paper is created only if the existing verified/published/aligned question pool and difficulty requirements pass; insufficient or identical pools skip generation.

RBI health now checks its registered official feed rather than its homepage, with HTTPS/same-host validation. Production probes on 2026-10-03 returned RBI 418 and PIB 403 Access Denied; access has NOT been restored. Quarantine and TLS validation remain enabled. Their failures do not stop the independent question-bank refresh. Official feed URLs were confirmed against the respective official RSS pages. Provider/hosting assistance is needed to resolve the source-side denial.

## Selective production install

Run `python3 scripts/automation/install.py` from a private checkout on the mcied45x cPanel Terminal. The installer verifies 12 exact runtime file hashes, makes private code/cron backups, takes the global scheduler lock and modifies only the marked MCI cron entry. cPanel automatically inserts its mandatory jailshell declaration; verification accepts only this observed server rewrite and still compares every job, comment and other environment value. It creates a small verified source page and up to 3 practice/1 mock/3 chapter sets in one transaction. Before committing it compares digests of old tests, old question content/options, pivots, attempts, enrollment and quota records. No migrations, live seeding or full repository deployment are performed. Unknown live edits stop installation. Verification failures roll back the transaction and restore reviewed code/cron; a failure after database commit may leave compatible added content in place. Private backups and the created-content manifest identify what was added.

Outputs are available in `storage/logs/scheduler.log`, `storage/logs/automation.log`, and the private installation log. Locks prevent concurrent runs; timeout expiry and nice priority reduce load but do not guarantee shared-host performance. A skipped minute while the lock is busy is intentional. A job killed by timeout may leave its Laravel overlap lock until the configured expiry; recovery of stale imports remains scheduled.

## Validation

61 relevant PHPUnit tests / 398 assertions and eight Python installer/scheduler checks pass. New coverage verifies monthly renewal, run caps, exam rotation, duplicate protection, old question/attempt preservation, trusted feed validation, source cursor recovery and GK/GS/Computer routing. Isolated SQLite deployment verification also runs before production installation.

Five pre-existing targeted tests fail on the unmodified parent: four assert a nonexistent `Question::is_verified` property, and one assumes seven active sources while the seed creates eight. Full-suite discovery also has a pre-existing private `GenerationReadinessTest::getConnection()` conflict with Laravel's protected method. These existing failures are not changed or masked by this work.
