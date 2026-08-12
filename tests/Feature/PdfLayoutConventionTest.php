<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PdfLayoutConventionTest extends TestCase
{
    public function test_only_approved_documents_use_landscape_orientation(): void
    {
        $controllers = collect(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Controllers')),
        ))
            ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
            ->mapWithKeys(fn (SplFileInfo $file): array => [
                $file->getPathname() => (string) file_get_contents($file->getPathname()),
            ])
            ->filter(fn (string $contents): bool => str_contains($contents, "'landscape'"));

        $this->assertCount(2, $controllers);

        $landscapeControllers = $controllers->keys()
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'MockExamWebController.php',
            'TeacherAttendanceSheetWebController.php',
        ], $landscapeControllers);

        $controllers->each(function (string $contents): void {
            $this->assertStringContainsString("->setPaper('a4', 'landscape')", $contents);
        });
    }

    public function test_wide_register_is_split_into_readable_portrait_sections(): void
    {
        $template = (string) file_get_contents(resource_path('views/grades/register-pdf.blade.php'));

        $this->assertStringContainsString('$assessments->chunk(5)', $template);
        $this->assertStringContainsString('register-section', $template);
    }

    public function test_individual_transcript_keeps_signatures_in_flow_and_dense_pages_safe(): void
    {
        $template = (string) file_get_contents(resource_path('views/report-cards/period-class-pdf.blade.php'));

        $this->assertStringContainsString('page-balanced', $template);
        $this->assertStringContainsString('page-dense', $template);
        $this->assertStringContainsString('$rows->count() > 10', $template);
        $this->assertStringContainsString('principal_name', $template);
        $this->assertStringNotContainsString('position: absolute', $template);
    }

    public function test_mock_exam_transcript_separates_panel_titles_from_score_tables(): void
    {
        $template = (string) file_get_contents(resource_path('views/mock-exams/transcripts-pdf.blade.php'));

        $this->assertStringContainsString('border-bottom: 2px solid #111', $template);
        $this->assertStringContainsString('.first-round .panel > .score-table { top: 30px; }', $template);
        $this->assertStringContainsString('.summary-round .panel > .score-table { top: 30px; }', $template);
    }
}
