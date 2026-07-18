<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_student_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type');
            $table->enum('scope', ['all', 'cycle', 'class'])->default('all');
            $table->string('level_cycle')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->index(['status', 'scope']);
            $table->unique(['document_type', 'scope', 'level_cycle', 'school_class_id'], 'required_documents_unique_scope');
        });

        $now = now();
        $defaults = [
            ['name' => 'Acte de naissance', 'document_type' => 'birth_certificate', 'position' => 1],
            ['name' => 'Photo', 'document_type' => 'photo', 'position' => 2],
            ['name' => 'Ancien bulletin', 'document_type' => 'previous_report_card', 'position' => 3],
            ['name' => 'Autorisation parentale', 'document_type' => 'parent_authorization', 'position' => 4],
        ];

        foreach ($defaults as $document) {
            DB::table('required_student_documents')->insert($document + [
                'scope' => 'all',
                'level_cycle' => null,
                'school_class_id' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('required_student_documents');
    }
};
