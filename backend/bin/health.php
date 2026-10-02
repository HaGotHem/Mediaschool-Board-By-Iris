<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
try { Board\Infrastructure\Database::connect()->query('SELECT 1'); exit(0); }
catch (Throwable) { fwrite(STDERR, "Base indisponible.\n"); exit(1); }
