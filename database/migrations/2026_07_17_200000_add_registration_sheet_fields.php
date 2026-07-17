<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('desired_class')->nullable()->after('photo_path');
            $table->string('origin_school')->nullable()->after('desired_class');
            $table->string('previous_class')->nullable()->after('origin_school');
            $table->string('repeated_class')->nullable()->after('previous_class');
            $table->string('nationality')->nullable()->after('address');
            $table->string('ethnicity')->nullable()->after('nationality');
            $table->string('religion')->nullable()->after('ethnicity');
            $table->string('sector')->nullable()->after('religion');
            $table->string('district')->nullable()->after('sector');
            $table->string('home_phone')->nullable()->after('district');
            $table->json('health_conditions')->nullable()->after('health_notes');
            $table->boolean('sport_aptitude')->nullable()->after('health_conditions');
            $table->string('emergency_contact_name')->nullable()->after('sport_aptitude');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('school_info_whatsapp')->nullable()->after('emergency_contact_phone');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('service')->nullable()->after('profession');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('service');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'desired_class',
                'origin_school',
                'previous_class',
                'repeated_class',
                'nationality',
                'ethnicity',
                'religion',
                'sector',
                'district',
                'home_phone',
                'health_conditions',
                'sport_aptitude',
                'emergency_contact_name',
                'emergency_contact_phone',
                'school_info_whatsapp',
            ]);
        });
    }
};
