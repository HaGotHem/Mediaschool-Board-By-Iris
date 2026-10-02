<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Board\Export\{SummaryExport, SummaryMailer};
$checks = 0;
function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
foreach (['=1+1', '+SUM(A1)', '-1+2', '@cmd', '  =1', "\tvalue"] as $cell) {
    check(str_starts_with(SummaryExport::safeCell($cell), "'"), 'Cellule dangereuse non neutralisée');
}
check(SummaryExport::safeCell('École IRIS') === 'École IRIS', 'Texte normal altéré');
$csv = SummaryExport::csv([['school' => 'École; "test"', 'entry_level' => 'B1', 'count' => 3], ['school' => '=1+1', 'entry_level' => 'B2', 'count' => 2]]);
check(str_starts_with($csv, "\xEF\xBB\xBF"), 'BOM absent');
$stream = fopen('php://temp','w+'); fwrite($stream, substr($csv, 3)); rewind($stream);
$rows = []; while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) { $rows[] = $row; } fclose($stream);
check($rows[1][0] === 'École; "test"', 'Échappement CSV erroné');
check($rows[2][0] === "'=1+1", 'Formule CSV non neutralisée');
check($rows[3] === ['TOTAL', '', '5'], 'Total CSV erroné');
check(str_contains(SummaryExport::csv([]), 'TOTAL;;0'), 'CSV vide inutilisable');
$pdf = SummaryExport::pdf('Salon fictif', []);
check(str_starts_with($pdf, '%PDF-'), 'PDF invalide');
putenv('SUMMARY_RECIPIENT_ALLOWLIST=demo@example.test');
SummaryMailer::assertRecipient('demo@example.test'); $checks++;
try { SummaryMailer::assertRecipient('autre@example.test'); throw new RuntimeException('Destinataire interdit accepté'); }
catch (DomainException) { $checks++; }
try { SummaryMailer::assertRecipient("bad\r\nBcc: x@example.test"); throw new RuntimeException('Injection adresse acceptée'); }
catch (InvalidArgumentException) { $checks++; }
echo "$checks vérifications du socle réussies. Ajoutez vos tests métier.\n";
