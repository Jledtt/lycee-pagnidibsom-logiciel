<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('label');
            $table->string('prefix')->nullable();
            $table->string('format');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->unsignedInteger('next_number')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->string('document_number')->nullable()->after('document_type');
            $table->index('document_number');
        });

        $now = now();

        foreach ([
            [
                'type' => 'student_matricule',
                'label' => 'Matricule élève',
                'prefix' => 'LPP',
                'format' => '{PREFIX}-{YEAR}-{NUMBER}',
                'padding' => 4,
            ],
            [
                'type' => 'payment_receipt',
                'label' => 'Recu de paiement',
                'prefix' => 'REC',
                'format' => '{PREFIX}-{DATE}-{NUMBER}',
                'padding' => 4,
            ],
            [
                'type' => 'student_certificate',
                'label' => 'Certificat',
                'prefix' => 'CERT',
                'format' => '{PREFIX}-{YEAR}-{NUMBER}',
                'padding' => 4,
            ],
        ] as $setting) {
            DB::table('numbering_settings')->insert($setting + [
                'next_number' => 1,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropIndex(['document_number']);
            $table->dropColumn('document_number');
        });

        Schema::dropIfExists('numbering_settings');
    }
};
