<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PdfLayoutConventionTest extends TestCase
{
    public function test_only_teacher_attendance_sheet_uses_landscape_orientation(): void
    {
        $controllers = collect(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Controllers')),
        ))
            ->filter(fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php')
            ->mapWithKeys(fn (SplFileInfo $file): array => [
                $file->getPathname() => (string) file_get_contents($file->getPathname()),
            ])
            ->filter(fn (string $contents): bool => str_contains($contents, "'landscape'"));

        $this->assertCount(1, $controllers);
        $this->assertStringEndsWith('TeacherAttendanceSheetWebController.php', $controllers->keys()->first());
        $this->assertStringContainsString("->setPaper('a4', 'landscape')", $controllers->first());
    }

    public function test_wide_register_is_split_into_readable_portrait_sections(): void
    {
        $template = (string) file_get_contents(resource_path('views/grades/register-pdf.blade.php'));

        $this->assertStringContainsString('$assessments->chunk(5)', $template);
        $this->assertStringContainsString('register-section', $template);
    }

    public function test_individual_transcript_balances_short_pages_and_keeps_dense_pages_safe(): void
    {
        $template = (string) file_get_contents(resource_path('views/report-cards/period-class-pdf.blade.php'));

        $this->assertStringContainsString('page-balanced', $template);
        $this->assertStringContainsString('page-dense', $template);
        $this->assertStringContainsString('bottom: 8px', $template);
    }
}
