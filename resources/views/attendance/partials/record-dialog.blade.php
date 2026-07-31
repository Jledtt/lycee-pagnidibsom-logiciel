@php
    $openRecordId = (int) session('attendance_record_open');
    $openRecord = $records->firstWhere('id', $openRecordId);
    $initialStatus = $openRecord?->status ?? 'absent';
    $statusLabels = ['absent' => 'Absence', 'late' => 'Retard', 'excused' => 'Justifié'];
@endphp

<x-ui.modal
    id="attendance-record-dialog"
    title="Traiter l’incident"
    description="Justifie l’absence ou replace l’élève parmi les présents."
    size="small"
    :open="(bool) $openRecord"
    data-attendance-record-dialog
>
    <div class="follow-up-summary-list">
        <div><span>Élève</span><strong data-attendance-student>{{ $openRecord?->student?->full_name ?? '-' }}</strong></div>
        <div><span>Date</span><strong data-attendance-date>{{ $openRecord?->session?->session_date?->format('d/m/Y') ?? '-' }}</strong></div>
        <div><span>Incident</span><strong data-attendance-status>{{ $statusLabels[$initialStatus] ?? $initialStatus }}</strong></div>
    </div>

    <form
        id="attendance-justify-form"
        method="POST"
        action="{{ $openRecord ? route('attendance.records.justify', $openRecord) : route('attendance.records.justify', 0) }}"
        data-attendance-justify-form
        data-prevent-double-submit
    >
        @csrf
        @method('PUT')
        <div class="field" style="margin-top:18px">
            <label for="attendance-justification-reason">Motif de justification</label>
            <textarea
                id="attendance-justification-reason"
                name="reason"
                minlength="3"
                maxlength="1000"
                placeholder="Exemple : certificat médical présenté…"
                required
                data-attendance-reason
            >{{ old('reason', $openRecord?->reason) }}</textarea>
            @error('reason') <small class="error">{{ $message }}</small> @enderror
        </div>
    </form>

    <form
        id="attendance-clear-form"
        method="POST"
        action="{{ $openRecord ? route('attendance.records.clear', $openRecord) : route('attendance.records.clear', 0) }}"
        data-attendance-clear-form
        data-prevent-double-submit
    >
        @csrf
        @method('DELETE')
    </form>

    <div class="dialog-action-note">
        <strong>Supprimer l’incident</strong>
        <span>L’élève sera remis présent. La ligne d’appel reste enregistrée.</span>
    </div>

    <x-slot:footer>
        <button class="btn btn-subtle" type="button" data-dialog-close>Fermer</button>
        <button class="btn btn-danger" type="submit" form="attendance-clear-form" data-submitting-label="Suppression…">Marquer présent</button>
        <button class="btn btn-primary" type="submit" form="attendance-justify-form" data-submitting-label="Justification…">Enregistrer la justification</button>
    </x-slot:footer>
</x-ui.modal>
