<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference lists from the old AMS. The legacy ids are kept as the primary
 * keys so enrollment foreign keys map 1:1 during the import; `legacy_key`
 * keeps the same value for traceability if a row is ever renumbered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mother_tongues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('religions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('indigenous_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indigenous_groups');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('mother_tongues');
    }
};
