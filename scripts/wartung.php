<?php

/*
 * Wird während eines Deploys als storage/framework/maintenance.php hochgeladen.
 * public/index.php lädt diese Datei vor allem anderen; so sieht niemand einen
 * halb hochgeladenen Stand. cron.php pausiert, solange sie existiert.
 */

http_response_code(503);
header('Retry-After: 60');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kurze Wartung</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 16px; background: #fafafa; color: #171717; }
        @media (prefers-color-scheme: dark) { body { background: #0a0a0a; color: #ededed; } }
        main { max-width: 28rem; text-align: center; }
    </style>
</head>
<body>
<main>
    <h1>Kurze Wartung</h1>
    <p>Gerade wird eine neue Version eingespielt. Bitte in einer Minute neu laden.</p>
</main>
</body>
</html>
<?php
exit;
