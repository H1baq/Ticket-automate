<?php

declare(strict_types=1);

/*
 * Fails when line coverage of app/Services is below the threshold.
 *
 * Usage: php check-services-coverage.php <clover.xml> <minimum-percent>
 */

[, $cloverPath, $minimum] = $argv + [null, 'coverage/clover.xml', '80'];

$clover = simplexml_load_file((string) $cloverPath);
if ($clover === false) {
    fwrite(STDERR, "Cannot read coverage report: {$cloverPath}\n");
    exit(2);
}

$statements = 0;
$covered = 0;
foreach ($clover->xpath('//file') ?: [] as $file) {
    if (! str_contains((string) $file['name'], '/app/Services/')) {
        continue;
    }

    $metrics = $file->metrics;
    $statements += (int) $metrics['statements'];
    $covered += (int) $metrics['coveredstatements'];
}

if ($statements === 0) {
    echo "No code in app/Services yet; coverage gate skipped.\n";
    exit(0);
}

$percent = round($covered / $statements * 100, 2);
echo "app/Services coverage: {$percent}% ({$covered}/{$statements} statements), minimum {$minimum}%\n";

exit($percent >= (float) $minimum ? 0 : 1);
