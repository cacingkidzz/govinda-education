<?php
declare(strict_types=1);

/**
 * Utilitas bersama halaman laporan.
 * Tidak boleh menghasilkan output sebelum header CSV dikirim.
 */

/**
 * Kirim file CSV (UTF-8 dengan BOM agar terbaca Excel) lalu hentikan eksekusi.
 *
 * @param array<int, string> $headers
 * @param array<int, array<int, string|int|float|null>> $rows
 */
function report_send_csv(string $filename, array $headers, array $rows): never
{
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

/**
 * Rentang tanggal laporan: default bulan berjalan.
 *
 * @return array{from:string,to:string,isDefault:bool}
 */
function report_date_range(): array
{
    $from = get_string('date_from');
    $to = get_string('date_to');
    $from = valid_date($from) ? $from : '';
    $to = valid_date($to) ? $to : '';

    $defaultFrom = date('Y-m-01');
    $defaultTo = date('Y-m-t');
    $isDefault = $from === '' && $to === '';

    if ($from === '' && $to === '') {
        $from = $defaultFrom;
        $to = $defaultTo;
    } elseif ($from === '') {
        $from = $to;
    } elseif ($to === '') {
        $to = $from;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    return ['from' => $from, 'to' => $to, 'isDefault' => $isDefault];
}

/**
 * Tombol ekspor CSV & cetak yang tidak ikut tercetak.
 */
function report_toolbar(string $csvUrl): string
{
    return '<div class="d-flex flex-wrap gap-2 no-print">'
        . '<a href="' . e($csvUrl) . '" class="btn btn-success">'
        . '<i class="bi bi-filetype-csv me-1"></i>Ekspor CSV</a>'
        . '<button type="button" class="btn btn-primary" onclick="window.print()">'
        . '<i class="bi bi-printer me-1"></i>Cetak</button>'
        . '</div>';
}
