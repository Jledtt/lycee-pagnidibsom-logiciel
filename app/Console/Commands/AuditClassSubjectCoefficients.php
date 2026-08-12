<?php

namespace App\Console\Commands;

use App\Models\ClassSubject;
use App\Rules\ValidClassSubjectCoefficient;
use Illuminate\Console\Command;

class AuditClassSubjectCoefficients extends Command
{
    protected $signature = 'lpp:audit-coefficients';

    protected $description = 'Liste les coefficients de matières hors de la grille officielle';

    public function handle(): int
    {
        $invalidAssignments = ClassSubject::query()
            ->with(['schoolClass', 'subject'])
            ->get()
            ->filter(fn (ClassSubject $assignment): bool => ! ValidClassSubjectCoefficient::isValid($assignment->coefficient))
            ->sortBy(fn (ClassSubject $assignment): string => ($assignment->schoolClass?->name ?? '').'|'.($assignment->subject?->name ?? ''));

        if ($invalidAssignments->isEmpty()) {
            $this->info('Aucun coefficient hors de la grille officielle.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Classe', 'Matière', 'Coefficient'],
            $invalidAssignments->map(fn (ClassSubject $assignment): array => [
                $assignment->id,
                $assignment->schoolClass?->name ?? '-',
                $assignment->subject?->name ?? '-',
                number_format((float) $assignment->coefficient, 2, '.', ''),
            ]),
        );
        $this->warn($invalidAssignments->count().' coefficient(s) à corriger manuellement. Aucune donnée n’a été modifiée.');

        return self::FAILURE;
    }
}
