<?php

namespace Tests\Feature;

use App\Models\SchoolSetting;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PdfBrandingTest extends TestCase
{
    public function test_pdf_logo_displays_the_configured_motto_underneath(): void
    {
        $school = new SchoolSetting([
            'logo_path' => 'images/logo-pagnidibsom.png',
            'motto' => '"Bâtir l\'excellence"',
        ]);

        $html = view('pdf.partials.logo-with-motto', [
            'school' => $school,
            'logoWidth' => 64,
        ])->render();

        $this->assertStringContainsString('alt="Logo"', $html);
        $this->assertStringContainsString('Bâtir l&#039;excellence', $html);
        $this->assertStringNotContainsString('&quot;Bâtir', $html);
    }

    public function test_pdf_templates_with_a_direct_logo_use_the_shared_branding_block(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views')),
        );

        $directLogoTemplates = collect($iterator)
            ->filter(fn (SplFileInfo $file): bool => $file->isFile() && str_ends_with($file->getFilename(), 'pdf.blade.php'))
            ->reject(fn (SplFileInfo $file): bool => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'partials'))
            ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), 'logo_path'));

        $this->assertNotEmpty($directLogoTemplates);

        foreach ($directLogoTemplates as $template) {
            $this->assertStringContainsString(
                'pdf.partials.logo-with-motto',
                (string) file_get_contents($template->getPathname()),
                "Le modèle {$template->getPathname()} affiche un logo sans la devise.",
            );
        }
    }
}
