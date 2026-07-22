<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $labels = [
        'Francais' => 'Français',
        'Mathematiques' => 'Mathématiques',
        'Mathematiques appliquees' => 'Mathématiques appliquées',
        'Histoire-Geographie' => 'Histoire-Géographie',
        'Education civique et morale' => 'Éducation civique et morale',
        'Technologies de l information et de la communication' => 'Technologies de l’information et de la communication',
        'Theatre' => 'Théâtre',
        'Art menager' => 'Art ménager',
    ];

    public function up(): void
    {
        $this->normalizeLabels($this->labels);
    }

    public function down(): void
    {
        $this->normalizeLabels(array_flip($this->labels));
    }

    private function normalizeLabels(array $labels): void
    {
        foreach ($labels as $from => $to) {
            if (Schema::hasTable('subjects')) {
                DB::table('subjects')->where('name', $from)->update(['name' => $to]);
            }

            if (Schema::hasTable('timetable_entries')) {
                DB::table('timetable_entries')->where('subject_name', $from)->update(['subject_name' => $to]);
            }

            if (Schema::hasTable('student_exit_authorizations')) {
                DB::table('student_exit_authorizations')->where('subject_name', $from)->update(['subject_name' => $to]);
            }
        }
    }
};
