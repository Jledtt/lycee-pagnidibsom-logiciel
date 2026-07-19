<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Timetable;
use Illuminate\Support\Str;

class TimetableTemplateService
{
    public function days(): array
    {
        return [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
        ];
    }

    public function periods(): array
    {
        return [
            ['sort_order' => 1, 'label' => '7h00-7h55', 'starts_at' => '07:00', 'ends_at' => '07:55', 'is_break' => false],
            ['sort_order' => 2, 'label' => '7h55-8h50', 'starts_at' => '07:55', 'ends_at' => '08:50', 'is_break' => false],
            ['sort_order' => 3, 'label' => '8h50-9h45', 'starts_at' => '08:50', 'ends_at' => '09:45', 'is_break' => false],
            ['sort_order' => 4, 'label' => 'RECREATION', 'starts_at' => null, 'ends_at' => null, 'is_break' => true],
            ['sort_order' => 5, 'label' => '10h10-11h05', 'starts_at' => '10:10', 'ends_at' => '11:05', 'is_break' => false],
            ['sort_order' => 6, 'label' => '11h05-12h00', 'starts_at' => '11:05', 'ends_at' => '12:00', 'is_break' => false],
            ['sort_order' => 7, 'label' => 'SOIR', 'starts_at' => null, 'ends_at' => null, 'is_break' => true],
            ['sort_order' => 8, 'label' => '15h00-16h00', 'starts_at' => '15:00', 'ends_at' => '16:00', 'is_break' => false],
            ['sort_order' => 9, 'label' => '16h00-17h00', 'starts_at' => '16:00', 'ends_at' => '17:00', 'is_break' => false],
        ];
    }

    public function seedBlankEntries(Timetable $timetable): void
    {
        $payload = [];

        foreach ($this->periods() as $period) {
            foreach (array_keys($this->days()) as $day) {
                $payload[] = $this->entryPayload($period, $day, $period['is_break'] ? $period['label'] : null);
            }
        }

        $timetable->entries()->delete();
        $timetable->entries()->createMany($payload);
    }

    public function applyExample(Timetable $timetable): bool
    {
        $classKey = $this->classKey($timetable->schoolClass);
        $template = $this->examples()[$classKey] ?? null;

        if (! $template) {
            return false;
        }

        $timetable->forceFill([
            'title' => 'Emploi du temps provisoire',
            'principal_teacher' => $template['teachers'],
            'notes' => 'Base importee depuis le modele 2025-2026. A adapter pour l annee en cours.',
            'status' => 'draft',
        ])->save();

        $payload = [];

        foreach ($this->periods() as $period) {
            foreach (array_keys($this->days()) as $day) {
                $subject = $period['is_break']
                    ? $period['label']
                    : ($template['grid'][$period['label']][$day] ?? null);

                $payload[] = $this->entryPayload($period, $day, $subject);
            }
        }

        $timetable->entries()->delete();
        $timetable->entries()->createMany($payload);

        return true;
    }

    public function classHasExample(?SchoolClass $schoolClass): bool
    {
        return $schoolClass && array_key_exists($this->classKey($schoolClass), $this->examples());
    }

    public function subjectOptions(): array
    {
        return Subject::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    private function entryPayload(array $period, string $day, ?string $subject): array
    {
        $subject = filled($subject) ? Str::of($subject)->squish()->toString() : null;

        return [
            'sort_order' => $period['sort_order'],
            'period_label' => $period['label'],
            'starts_at' => $period['starts_at'],
            'ends_at' => $period['ends_at'],
            'day_of_week' => $day,
            'subject_name' => $subject,
            'is_break' => $period['is_break'],
        ];
    }

    private function classKey(SchoolClass $schoolClass): string
    {
        return Str::of($schoolClass->name)
            ->lower()
            ->replace(['eme', 'eme', '6eme', '5eme', '4eme', '3eme'], ['e', 'e', '6e', '5e', '4e', '3e'])
            ->replaceMatches('/\\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function examples(): array
    {
        return [
            '6e' => [
                'teachers' => 'ZONGO Florence (Francais); KIEMA Philomene (Anglais); KOMBASSERE Salifou (HG); BADO Constant (Maths); KEREGUE (EC); KABORE Aichatou (SVT); MEDA Mathurin (EPS)',
                'grid' => [
                    '7h00-7h55' => ['monday' => 'EC', 'tuesday' => 'Francais', 'wednesday' => null, 'thursday' => 'Francais', 'friday' => 'SVT', 'saturday' => 'HG'],
                    '7h55-8h50' => ['monday' => 'EC', 'tuesday' => 'Francais', 'wednesday' => 'Anglais', 'thursday' => 'Francais', 'friday' => 'SVT', 'saturday' => 'HG'],
                    '8h50-9h45' => ['monday' => null, 'tuesday' => 'Maths', 'wednesday' => 'Anglais', 'thursday' => null, 'friday' => 'Devoir', 'saturday' => 'EPS'],
                    '10h10-11h05' => ['monday' => 'HG', 'tuesday' => 'Maths', 'wednesday' => null, 'thursday' => 'Maths', 'friday' => 'Devoir', 'saturday' => 'Francais'],
                    '11h05-12h00' => ['monday' => 'HG', 'tuesday' => null, 'wednesday' => 'SVT', 'thursday' => 'Maths', 'friday' => 'Devoir', 'saturday' => 'Francais'],
                    '15h00-16h00' => ['monday' => 'Anglais', 'tuesday' => 'EPS', 'wednesday' => null, 'thursday' => null, 'friday' => 'Anglais', 'saturday' => null],
                    '16h00-17h00' => ['monday' => 'Maths', 'tuesday' => 'EPS', 'wednesday' => null, 'thursday' => null, 'friday' => 'Anglais', 'saturday' => null],
                ],
            ],
            '5e' => [
                'teachers' => 'BADIEL Philippe (Francais); DEMBELE/SAWADOGO Bibata (Anglais); KEREGUE Sompeguea (HG); DIANDA Halidou (Maths); KEREGUE Sompeguea (EC); KABORE/KABRE Aichatou (SVT); NIKIEMA Zakaria (EPS)',
                'grid' => [
                    '7h00-7h55' => ['monday' => 'Maths', 'tuesday' => 'HG', 'wednesday' => 'Maths', 'thursday' => 'HG', 'friday' => 'Francais', 'saturday' => 'Maths'],
                    '7h55-8h50' => ['monday' => 'EPS', 'tuesday' => 'HG', 'wednesday' => 'Maths', 'thursday' => 'HG', 'friday' => 'Francais', 'saturday' => 'Maths'],
                    '8h50-9h45' => ['monday' => 'Francais', 'tuesday' => null, 'wednesday' => 'EC', 'thursday' => 'SVT', 'friday' => 'EC', 'saturday' => null],
                    '10h10-11h05' => ['monday' => 'SVT', 'tuesday' => 'Devoir', 'wednesday' => null, 'thursday' => 'SVT', 'friday' => 'Anglais', 'saturday' => 'Francais'],
                    '11h05-12h00' => ['monday' => null, 'tuesday' => 'Devoir', 'wednesday' => 'Anglais', 'thursday' => null, 'friday' => 'Anglais', 'saturday' => null],
                    '15h00-16h00' => ['monday' => null, 'tuesday' => 'Anglais', 'wednesday' => null, 'thursday' => 'EPS', 'friday' => null, 'saturday' => null],
                    '16h00-17h00' => ['monday' => null, 'tuesday' => 'Anglais', 'wednesday' => null, 'thursday' => 'EPS', 'friday' => null, 'saturday' => null],
                ],
            ],
            '4e' => [
                'teachers' => 'ZONGO Florence (Francais); KIEMA Philomene (Anglais); DAH Sami Sie (HG); KAMANA Payaki (Maths); KOMBASSERE Salifou (EC); KOIBA Issaka (SVT); OUEDRAOGO Hilaire (EPS)',
                'grid' => [
                    '7h00-7h55' => ['monday' => 'EPS', 'tuesday' => 'PC', 'wednesday' => 'Anglais', 'thursday' => 'PC', 'friday' => 'Maths', 'saturday' => 'EPS'],
                    '7h55-8h50' => ['monday' => 'EPS', 'tuesday' => 'PC', 'wednesday' => null, 'thursday' => 'PC', 'friday' => 'Maths', 'saturday' => null],
                    '8h50-9h45' => ['monday' => 'SVT', 'tuesday' => 'Francais', 'wednesday' => null, 'thursday' => null, 'friday' => null, 'saturday' => 'Maths'],
                    '10h10-11h05' => ['monday' => 'Anglais', 'tuesday' => 'HG', 'wednesday' => 'Maths', 'thursday' => 'HG', 'friday' => 'SVT', 'saturday' => 'EC'],
                    '11h05-12h00' => ['monday' => 'Anglais', 'tuesday' => 'HG', 'wednesday' => 'Maths', 'thursday' => 'HG', 'friday' => 'SVT', 'saturday' => 'EC'],
                    '15h00-16h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => 'Francais', 'thursday' => null, 'friday' => 'Francais', 'saturday' => null],
                    '16h00-17h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => 'Francais', 'thursday' => null, 'friday' => 'Francais', 'saturday' => null],
                ],
            ],
            '3e' => [
                'teachers' => 'SONG-NABA Belko Leon (Francais); TINTILA Yamdaogo (Anglais); KOMBASSERE Salifou (HG); BADO Constant (Maths); KOIBA Issaka (SVT); OUEDRAOGO Hilaire (EPS)',
                'grid' => [
                    '7h00-7h55' => ['monday' => 'SVT', 'tuesday' => 'Maths', 'wednesday' => 'Francais', 'thursday' => 'Anglais', 'friday' => 'SVT', 'saturday' => 'Francais'],
                    '7h55-8h50' => ['monday' => 'SVT', 'tuesday' => 'Maths', 'wednesday' => 'Francais', 'thursday' => 'Anglais', 'friday' => 'SVT', 'saturday' => 'Francais'],
                    '8h50-9h45' => ['monday' => 'EPS', 'tuesday' => 'Anglais', 'wednesday' => 'Devoir', 'thursday' => 'Maths', 'friday' => null, 'saturday' => null],
                    '10h10-11h05' => ['monday' => 'PC', 'tuesday' => 'Devoir', 'wednesday' => 'Devoir', 'thursday' => 'PC', 'friday' => 'Francais', 'saturday' => 'Maths'],
                    '11h05-12h00' => ['monday' => 'PC', 'tuesday' => 'Devoir', 'wednesday' => 'Devoir', 'thursday' => 'PC', 'friday' => 'Francais', 'saturday' => 'Maths'],
                    '15h00-16h00' => ['monday' => 'HG', 'tuesday' => null, 'wednesday' => null, 'thursday' => 'EPS', 'friday' => 'HG', 'saturday' => null],
                    '16h00-17h00' => ['monday' => 'HG', 'tuesday' => null, 'wednesday' => null, 'thursday' => 'EPS', 'friday' => 'HG', 'saturday' => null],
                ],
            ],
            '2nde c' => [
                'teachers' => 'BADIEL Philippe (Francais); DEMBELE/SAWADOGO Bibata (Anglais); KEREGUE Sompeguea (HG); KAMANA Payaki (Maths); KABORE/KABRE Aichatou (SVT); NIKIEMA Zakaria (EPS); DAH Sam Sie (EC)',
                'grid' => [
                    '7h00-7h55' => ['monday' => 'Francais', 'tuesday' => 'EC', 'wednesday' => 'HG', 'thursday' => 'Maths', 'friday' => 'EPS', 'saturday' => 'EPS'],
                    '7h55-8h50' => ['monday' => 'Francais', 'tuesday' => 'EC', 'wednesday' => 'HG', 'thursday' => 'Maths', 'friday' => null, 'saturday' => 'Francais'],
                    '8h50-9h45' => ['monday' => 'HG', 'tuesday' => 'Maths', 'wednesday' => 'SVT', 'thursday' => 'Philo', 'friday' => 'SVT', 'saturday' => 'Francais'],
                    '10h10-11h05' => ['monday' => 'PC', 'tuesday' => 'Maths', 'wednesday' => 'SVT', 'thursday' => 'Philo', 'friday' => 'PC', 'saturday' => 'Maths'],
                    '11h05-12h00' => ['monday' => 'PC', 'tuesday' => 'Anglais', 'wednesday' => null, 'thursday' => null, 'friday' => 'PC', 'saturday' => 'Maths'],
                    '15h00-16h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => 'PC', 'thursday' => 'Anglais', 'friday' => null, 'saturday' => null],
                    '16h00-17h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => 'PC', 'thursday' => 'Anglais', 'friday' => null, 'saturday' => null],
                ],
            ],
            '2nde a' => [
                'teachers' => 'SONG-NABA Belko Leon (Francais); TINTILA Yamdaogo (Anglais); DAH Sam Sie (EC); KAMANA Payaki (Maths); MEDA Mathurin (EPS); KEREGUE Sompeguea (HG); MORE Tolfanrson (Philo); M. SAWADOGO (Allemand)',
                'grid' => [
                    '7h00-7h55' => ['monday' => null, 'tuesday' => 'EPS', 'wednesday' => 'EC', 'thursday' => 'Francais', 'friday' => 'HG', 'saturday' => 'Maths'],
                    '7h55-8h50' => ['monday' => 'Philo', 'tuesday' => 'EPS', 'wednesday' => null, 'thursday' => 'Francais', 'friday' => 'HG', 'saturday' => 'Maths'],
                    '8h50-9h45' => ['monday' => 'Philo', 'tuesday' => 'HG', 'wednesday' => 'Francais', 'thursday' => 'Maths', 'friday' => null, 'saturday' => 'Francais'],
                    '10h10-11h05' => ['monday' => 'Allemand', 'tuesday' => 'HG', 'wednesday' => 'Francais', 'thursday' => 'Anglais', 'friday' => 'Allemand', 'saturday' => 'Allemand'],
                    '11h05-12h00' => ['monday' => 'Allemand', 'tuesday' => 'Anglais', 'wednesday' => null, 'thursday' => 'Anglais', 'friday' => 'Allemand', 'saturday' => 'Allemand'],
                    '15h00-16h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => null, 'thursday' => null, 'friday' => null, 'saturday' => null],
                    '16h00-17h00' => ['monday' => null, 'tuesday' => null, 'wednesday' => null, 'thursday' => null, 'friday' => null, 'saturday' => null],
                ],
            ],
        ];
    }
}
