<?php

namespace Tests\Feature;

use App\Services\CsvExportService;
use Tests\TestCase;

class CsvExportServiceTest extends TestCase
{
    public function test_dangerous_spreadsheet_formula_prefixes_are_escaped(): void
    {
        $values = [
            '=SUM(A1:A2)',
            '+cmd',
            '-1+2',
            '@SUM(A1:A2)',
            "\t=SUM(A1:A2)",
            "\r=SUM(A1:A2)",
        ];

        $rows = $this->exportRows($values);

        foreach ($values as $index => $value) {
            $this->assertSame("'".$value, $rows[$index][0]);
        }
    }

    public function test_safe_text_and_negative_numbers_are_not_modified(): void
    {
        $rows = $this->exportRows(['Texte normal', -2500]);

        $this->assertSame('Texte normal', $rows[0][0]);
        $this->assertSame('-2500', $rows[1][0]);
    }

    private function exportRows(array $values): array
    {
        $response = app(CsvExportService::class)->download(
            'test.csv',
            ['Valeur'],
            array_map(fn ($value) => [$value], $values),
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        fgetcsv($handle, null, ';');
        $rows = [];

        while (($row = fgetcsv($handle, null, ';')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
