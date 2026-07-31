<x-ui.modal
    id="app-confirmation-dialog"
    title="Confirmer l’action"
    description="Vérifie les conséquences avant de continuer."
    size="small"
>
    <div class="confirmation-summary">
        <span>Objet concerné</span>
        <strong data-confirmation-object>Action sélectionnée</strong>
        <p data-confirmation-message>Cette action modifiera les données enregistrées.</p>
    </div>

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Annuler</button>
        <button class="btn btn-danger" type="button" data-confirmation-submit>Confirmer</button>
    </x-slot:footer>
</x-ui.modal>
