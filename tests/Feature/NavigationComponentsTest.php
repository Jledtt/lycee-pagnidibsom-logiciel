<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class NavigationComponentsTest extends TestCase
{
    public function test_active_navigation_section_is_open_and_current_link_is_announced(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-navigation.section title="Élèves" :active="true">
                <x-navigation.link href="/students" :active="true">Dossiers élèves</x-navigation.link>
                <x-navigation.link href="/enrollments">Inscriptions</x-navigation.link>
            </x-navigation.section>
        BLADE);

        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('active-section', $html);
        $this->assertStringContainsString(' open', $html);
        $this->assertStringContainsString('<summary class="nav-section-title">', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }
}
