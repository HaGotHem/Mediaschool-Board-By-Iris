<?php
declare(strict_types=1);
if (strlen(getenv('APP_SECRET') ?: '') < 32) { fwrite(STDERR, "APP_SECRET doit contenir au moins 32 caractères.\n"); exit(1); }
if ((getenv('APP_ENV') ?: '') === 'production') {
    foreach (['APP_SECRET', 'DB_PASSWORD'] as $key) {
        $value = getenv($key) ?: '';
        if (strlen($value) < 24 || str_contains(strtolower($value), 'change')) {
            fwrite(STDERR, "Secret de production absent ou provisoire : $key.\n"); exit(1);
        }
    }
    $url = getenv('APP_URL') ?: '';
    if (!str_starts_with($url, 'https://') || str_contains(strtoupper($url), 'REMPLACER')
        || filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        fwrite(STDERR, "Production : URL HTTPS réelle et APP_DEBUG=false requis.\n"); exit(1);
    }
}
echo "Configuration structurelle valide. La recette métier reste obligatoire.\n";
