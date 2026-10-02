<?php
declare(strict_types=1);
namespace Board\Export;
use Dompdf\Dompdf;
use Dompdf\Options;
final class SummaryExport
{
    public static function safeCell(string $value): string
    {
        // Neutralise aussi les formules précédées d'espaces, tabs ou sauts de ligne.
        return preg_match('/^[\s]*[=+@-]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'" . $value : $value;
    }
    public static function csv(array $groups): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['École', 'Niveau', 'Nombre'], ';', '"', '');
        $total = 0;
        foreach ($groups as $group) {
            $count = (int)$group['count']; $total += $count;
            fputcsv($stream, [self::safeCell($group['school']), self::safeCell($group['entry_level']), $count], ';', '"', '');
        }
        fputcsv($stream, ['TOTAL', '', $total], ';', '"', '');
        rewind($stream); $content = stream_get_contents($stream); fclose($stream);
        return $content;
    }
    public static function pdf(string $event, array $groups, string $scope = 'Toutes les écoles et tous les niveaux'): string
    {
        $escape = fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = ''; $total = 0;
        foreach ($groups as $group) {
            $count = (int)$group['count']; $total += $count;
            $rows .= '<tr><td>' . $escape($group['school']) . '</td><td>' . $escape($group['entry_level']) . '</td><td>' . $count . '</td></tr>';
        }
        $time = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('d/m/Y H:i');
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#172033}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{padding:9px;border:1px solid #ccd3df;text-align:left}th{background:#e9eef5}thead{display:table-header-group}tr{page-break-inside:avoid}</style></head><body><h1>Mediaschool Board by Iris Nice</h1><h2>'
            . $escape($event) . '</h2><p>Généré le ' . $time . ' — Europe/Paris</p><p>Périmètre : ' . $escape($scope)
            . '</p><table><thead><tr><th>École</th><th>Niveau</th><th>Nombre</th></tr></thead><tbody>' . $rows
            . '<tr><td><strong>TOTAL</strong></td><td></td><td><strong>' . $total . '</strong></td></tr></tbody></table></body></html>';
        $options = new Options(); $options->set('isRemoteEnabled', false); $options->set('isPhpEnabled', false);
        $pdf = new Dompdf($options); $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4'); $pdf->render();
        return $pdf->output();
    }
}
