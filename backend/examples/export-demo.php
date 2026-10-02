<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Board\Export\SummaryExport;
// Données fictives agrégées uniquement. Pas de requête DB, pas de mail.
$groups = [
    ['school' => 'IRIS', 'entry_level' => 'Iris > BTS 1', 'count' => 12],
    ['school' => 'ECS', 'entry_level' => 'B1', 'count' => 7],
];
$target = $argv[1] ?? sys_get_temp_dir();
if (!is_dir($target) || !is_writable($target)) { fwrite(STDERR, "Dossier de sortie inaccessible.\n"); exit(1); }
file_put_contents($target . '/recap-demo.csv', SummaryExport::csv($groups));
file_put_contents($target . '/recap-demo.pdf', SummaryExport::pdf('Salon de démonstration — données fictives', $groups));
echo "Exemples écrits dans $target. Les routes d'export restent à développer.\n";
