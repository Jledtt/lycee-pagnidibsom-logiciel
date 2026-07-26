<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunicationTemplateService
{
    public function ensureDefaults(): Collection
    {
        return collect($this->defaults())->map(function (array $definition, string $code) {
            return CommunicationTemplate::query()->firstOrCreate(
                ['code' => $code],
                $definition + ['code' => $code],
            );
        });
    }

    public function reset(CommunicationTemplate $template, ?User $user = null): CommunicationTemplate
    {
        $definition = $this->defaults()[$template->code] ?? null;

        abort_if(! $definition, 404);

        $template->update($definition + [
            'is_active' => true,
            'updated_by' => $user?->id,
        ]);

        return $template->refresh();
    }

    public function render(string $code, array $variables): ?array
    {
        $definition = $this->defaults()[$code] ?? null;

        if (! $definition) {
            return null;
        }

        $template = CommunicationTemplate::query()->firstOrCreate(
            ['code' => $code],
            $definition + ['code' => $code],
        );

        if (! $template->is_active) {
            return null;
        }

        return [
            'subject' => $this->sanitizeSubject($this->replaceVariables($template->subject, $variables)),
            'body' => $this->replaceVariables($template->body, $variables),
        ];
    }

    public function replaceVariables(string $text, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $matches) => (string) ($variables[$matches[1]] ?? ''),
            $text,
        );
    }

    public function sanitizeSubject(string $subject): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
    }

    public function defaults(): array
    {
        return [
            'payment_received' => [
                'name' => 'Paiement reçu',
                'subject' => 'Reçu {{ receipt_number }} - {{ student_name }}',
                'body' => "Bonjour {{ recipient_name }},\n\nNous confirmons le paiement enregistré pour {{ student_name }}.\n\nMontant : {{ amount }} FCFA\nDate : {{ payment_date }}\nReçu : {{ receipt_number }}\nClasse : {{ class_name }}\n\nMerci de conserver ce numéro de reçu.\n\nLycée Privé Pagnidibsom",
                'available_variables' => [
                    'recipient_name',
                    'student_name',
                    'amount',
                    'payment_date',
                    'receipt_number',
                    'class_name',
                ],
                'is_active' => true,
            ],
            'attendance_alert' => [
                'name' => 'Absence ou retard',
                'subject' => 'Assiduité de {{ student_name }} - {{ attendance_date }}',
                'body' => "Bonjour {{ recipient_name }},\n\n{{ student_name }} a été marqué(e) {{ attendance_status }} le {{ attendance_date }} dans la classe {{ class_name }}.{{ minutes_late_line }}{{ reason_line }}\n\nPour toute précision, veuillez contacter l'administration.\n\nLycée Privé Pagnidibsom",
                'available_variables' => [
                    'recipient_name',
                    'student_name',
                    'attendance_status',
                    'attendance_date',
                    'class_name',
                    'minutes_late_line',
                    'reason_line',
                ],
                'is_active' => true,
            ],
            'student_status_changed' => [
                'name' => 'Changement de statut d’un élève',
                'subject' => 'Mise à jour du dossier de {{ student_name }}',
                'body' => "Bonjour {{ recipient_name }},\n\nLe statut scolaire de {{ student_name }} a été modifié.\n\nAncien statut : {{ old_status }}\nNouveau statut : {{ new_status }}\nDate : {{ changed_at }}\n\nPour toute précision, veuillez contacter l'administration.\n\nLycée Privé Pagnidibsom",
                'available_variables' => [
                    'recipient_name',
                    'student_name',
                    'old_status',
                    'new_status',
                    'changed_at',
                ],
                'is_active' => true,
            ],
        ];
    }
}
