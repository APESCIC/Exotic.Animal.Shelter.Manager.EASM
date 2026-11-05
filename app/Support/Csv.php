<?php

namespace App\Support;

class Csv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function make(array $header, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
