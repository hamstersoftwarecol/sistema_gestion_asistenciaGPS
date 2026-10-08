<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Descarga CSV con BOM UTF-8 para que Excel muestre bien las tildes.
     *
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_values($row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
