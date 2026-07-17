<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('school_name');
            $table->string('motto')->nullable()->after('logo_path');
            $table->string('country')->nullable()->after('motto');
            $table->string('national_motto')->nullable()->after('country');
            $table->string('city')->nullable()->after('national_motto');
            $table->string('postal_box')->nullable()->after('city');
            $table->string('website')->nullable()->after('email');
            $table->string('principal_title')->default('Le Proviseur')->after('principal_name');
        });

        DB::table('school_settings')->update([
            'short_name' => 'LPP',
            'address' => '04 Ouagadougou 04 BP 8825',
            'phone' => '(+226) 72 81 61 59 / 78 42 62 06',
            'email' => 'infoslyceepagnidibsom@gmail.com',
            'logo_path' => 'images/logo-pagnidibsom.png',
            'motto' => '"Batir l\'excellence"',
            'country' => 'Burkina Faso',
            'national_motto' => 'La Patrie ou la Mort Nous Vaincrons',
            'city' => 'Ouagadougou',
            'postal_box' => '04 BP 8825',
            'principal_name' => 'Yamdaogo TINTILA',
            'principal_title' => 'Le Proviseur',
        ]);
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'motto',
                'country',
                'national_motto',
                'city',
                'postal_box',
                'website',
                'principal_title',
            ]);
        });
    }
};
