<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->string('conduct')->nullable()->after('decision');
            $table->string('distinction')->nullable()->after('conduct');
            $table->decimal('absence_hours', 6, 2)->nullable()->after('distinction');
            $table->unsignedSmallInteger('class_size_ranked')->nullable()->after('class_size');
            $table->unsignedSmallInteger('class_size_unranked')->nullable()->after('class_size_ranked');
        });
    }

    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropColumn([
                'conduct',
                'distinction',
                'absence_hours',
                'class_size_ranked',
                'class_size_unranked',
            ]);
        });
    }
};
