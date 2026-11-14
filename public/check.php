<?php

use Easm\Requirements\HostEnvironment;
use Easm\Requirements\RequirementsChecker;

/**
 * Standalone pre-install requirements checker for EASM.
 * Plain PHP 7.4+ — does not load Laravel. Open /check.php in a browser before /install.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$basePath = dirname(__DIR__);
$reqDir = $basePath.'/install/requirements';

if (! is_file($reqDir.'/HostEnvironment.php') || ! is_file($reqDir.'/RequirementsChecker.php')) {
    header('Content-Type: text/plain; charset=UTF-8', true, 500);
    echo "EASM requirements files are missing. Upload the full release package, including install/requirements/.\n";
    exit(1);
}

require_once $reqDir.'/HostEnvironment.php';
require_once $reqDir.'/RequirementsChecker.php';

$env = new HostEnvironment($basePath);
$report = (new RequirementsChecker($env))->run();

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$statusLabel = $report['locked'] ? 'Locked' : ($report['ok'] ? 'Ready' : 'Not ready');
?><!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EASM host check — <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; margin: 0; background: #f6f3ee; color: #1c1a16; }
        main { max-width: 44rem; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        h1 { font-size: 1.75rem; margin: 0 0 0.5rem; }
        .lead { margin: 0 0 1.5rem; color: #4a453c; }
        .banner { padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-left: 4px solid #2f6f5e; background: #e7f2ee; }
        .banner.fail { border-color: #9b2c2c; background: #f8e8e8; }
        .banner.warn { border-color: #9a6700; background: #f8f0de; }
        .check { padding: 0.9rem 0; border-bottom: 1px solid #ddd6c8; }
        .check:last-child { border-bottom: 0; }
        .row { display: flex; gap: 0.75rem; align-items: baseline; justify-content: space-between; }
        .label { font-weight: 700; }
        .badge { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .pass .badge { color: #2f6f5e; }
        .fail .badge { color: #9b2c2c; }
        .warn .badge { color: #9a6700; }
        .detail, .fix { margin: 0.35rem 0 0; font-size: 0.95rem; color: #4a453c; }
        .fix { color: #1c1a16; }
        a.button { display: inline-block; margin-top: 1.5rem; padding: 0.65rem 1rem; background: #2f6f5e; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <h1>EASM host check</h1>
    <p class="lead">Diagnose this host before the install wizard. No Laravel code is loaded here.</p>

    <?php if ($report['locked']) { ?>
        <div class="banner fail"><strong>Already installed.</strong> This checker is locked on an installed shelter.</div>
    <?php } elseif ($report['ok']) { ?>
        <div class="banner"><strong>Ready.</strong> Required checks passed. You can continue to the install wizard.</div>
    <?php } else { ?>
        <div class="banner fail"><strong>Not ready.</strong> Fix the failed checks below, then reload this page.</div>
    <?php } ?>

    <?php foreach ($report['checks'] as $check) { ?>
        <div class="check <?php echo htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row">
                <span class="label"><?php echo htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="badge"><?php echo htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p class="detail"><?php echo htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($check['fix'] !== '') { ?>
                <p class="fix"><strong>Fix:</strong> <?php echo htmlspecialchars($check['fix'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (! $report['locked'] && $report['ok']) { ?>
        <a class="button" href="/install">Continue to install</a>
    <?php } ?>
</main>
</body>
</html>
