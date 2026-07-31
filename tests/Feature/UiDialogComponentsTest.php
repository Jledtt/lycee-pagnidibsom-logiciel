<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiDialogComponentsTest extends TestCase
{
    public function test_modal_has_accessible_structure_and_optional_footer(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.modal id="payment-dialog" title="Nouveau paiement" description="Enregistrer un règlement.">
                <form data-prevent-double-submit><label for="amount">Montant</label><input id="amount" name="amount"></form>
                <x-slot:footer><button type="submit">Enregistrer</button></x-slot:footer>
            </x-ui.modal>
        BLADE);

        $this->assertStringContainsString('<dialog', $html);
        $this->assertStringContainsString('id="payment-dialog"', $html);
        $this->assertStringContainsString('aria-labelledby="payment-dialog-title"', $html);
        $this->assertStringContainsString('aria-describedby="payment-dialog-description"', $html);
        $this->assertStringContainsString('aria-label="Fermer"', $html);
        $this->assertStringContainsString('ui-dialog__footer', $html);
    }

    public function test_non_dismissible_modal_can_open_with_validation_content_visible_without_javascript(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.modal id="validation-dialog" title="Corriger le formulaire" :dismissible="false" :open="true">
                <p>Le montant est obligatoire.</p>
            </x-ui.modal>
        BLADE);

        $this->assertStringContainsString('data-dismissible="false"', $html);
        $this->assertStringContainsString('open data-dialog-open-on-load', $html);
        $this->assertStringNotContainsString('aria-label="Fermer"', $html);
    }

    public function test_drawer_uses_the_same_accessible_contract(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.drawer id="student-summary" title="Situation de l’élève" description="Résumé financier." :wide="true">
                <p>Reste à payer : 25 000 FCFA</p>
            </x-ui.drawer>
        BLADE);

        $this->assertStringContainsString('<dialog', $html);
        $this->assertStringContainsString('id="student-summary"', $html);
        $this->assertStringContainsString('ui-drawer--wide', $html);
        $this->assertStringContainsString('aria-labelledby="student-summary-title"', $html);
        $this->assertStringContainsString('aria-describedby="student-summary-description"', $html);
    }
}
