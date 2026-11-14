<?php

use Easm\Recovery\RecoveryActions;
use Easm\Recovery\RecoveryToken;

/**
 * Standalone recovery page for EASM when Laravel will not boot.
 * Plain PHP — does not load vendor/ or Laravel. Enabled only while
 * storage/app/update/recovery.token exists; open /recover.php?token=…
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$basePath = dirname(__DIR__);
$recoveryDir = $basePath.'/install/recovery';

$recoveryReady = is_file($recoveryDir.'/RecoveryToken.php')
    && is_file($recoveryDir.'/RecoveryActions.php')
    && is_file($recoveryDir.'/EnvFile.php');

if (! $recoveryReady) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "EASM recovery files are missing. Upload the full release package, including install/recovery/.\n";
} else {
    require_once $recoveryDir.'/EnvFile.php';
    require_once $recoveryDir.'/RecoveryToken.php';
    require_once $recoveryDir.'/RecoveryActions.php';

    $updateRoot = $basePath.'/storage/app/update';
    $tokenStore = new RecoveryToken($updateRoot);
    $provided = isset($_POST['token']) ? (string) $_POST['token'] : (isset($_GET['token']) ? (string) $_GET['token'] : '');

    header('X-Robots-Tag: noindex');
    header('Cache-Control: no-store');

    if (! $tokenStore->matches($provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Forbidden. Recovery is only available during an update, with the correct token.\n";
    } else {
        $message = null;
        $error = null;
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($requestMethod === 'POST') {
            $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
            $actions = new RecoveryActions($basePath);

            try {
                if ($action === 'maintenance_off') {
                    $result = $actions->maintenanceOff();
                } elseif ($action === 'finish') {
                    $result = $actions->finish();
                } elseif ($action === 'rollback') {
                    $result = $actions->rollback();
                } else {
                    $result = ['ok' => false, 'message' => 'Unknown recovery action.'];
                }

                if ($result['ok']) {
                    $tokenStore->clear();
                    $message = $result['message'].' The recovery token has been removed.';
                } else {
                    $error = $result['message'];
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=UTF-8');
        $tokenEsc = htmlspecialchars($provided, ENT_QUOTES, 'UTF-8');
        ?><!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EASM recovery</title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; margin: 0; background: #f6f3ee; color: #1c1a16; }
        main { max-width: 40rem; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        h1 { font-size: 1.75rem; margin: 0 0 0.5rem; }
        .lead { margin: 0 0 1.5rem; color: #4a453c; }
        .banner { padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-left: 4px solid #2f6f5e; background: #e7f2ee; }
        .banner.fail { border-color: #9b2c2c; background: #f8e8e8; }
        form { margin: 0 0 1rem; padding: 1rem 0; border-bottom: 1px solid #ddd6c8; }
        button { font: inherit; padding: 0.55rem 1rem; background: #2f6f5e; color: #fff; border: 0; cursor: pointer; }
        button.danger { background: #9b2c2c; }
        .hint { color: #4a453c; font-size: 0.95rem; margin: 0.35rem 0 0; }
    </style>
</head>
<body>
<main>
    <h1>EASM recovery</h1>
    <p class="lead">Use this page when an update left the site broken and Laravel will not boot. It does not load <code>vendor/</code>.</p>

    <?php if ($message !== null) { ?>
        <div class="banner"><strong>Done.</strong> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <p><a href="/">Return to the site</a></p>
    <?php } else { ?>
        <?php if ($error !== null) { ?>
            <div class="banner fail"><strong>Failed.</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <form method="post">
            <input type="hidden" name="token" value="<?php echo $tokenEsc; ?>">
            <input type="hidden" name="action" value="maintenance_off">
            <button type="submit">Turn maintenance mode off</button>
            <p class="hint">Removes <code>storage/framework/down</code> so the site can answer again.</p>
        </form>

        <form method="post">
            <input type="hidden" name="token" value="<?php echo $tokenEsc; ?>">
            <input type="hidden" name="action" value="finish">
            <button type="submit">Finish / clear update session</button>
            <p class="hint">Clears the updater lock and state, and turns maintenance mode off. Keeps the current code.</p>
        </form>

        <form method="post">
            <input type="hidden" name="token" value="<?php echo $tokenEsc; ?>">
            <input type="hidden" name="action" value="rollback">
            <button type="submit" class="danger">Roll back to previous version</button>
            <p class="hint">Restores the pre-swap code snapshot and the pre-update database backup, then turns maintenance off.</p>
        </form>
    <?php } ?>
</main>
</body>
</html>
        <?php
    }
}
