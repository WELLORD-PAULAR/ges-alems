<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One detail row per enrollment. The old app allowed duplicate child rows; the
 * import keeps the newest by `id DESC` and logs the rest, so each new table has
 * a unique `enrollment_id`.
 *
 * Legacy column mapping (typos fixed):
 *   ca_provice  -> current_province
 *   pa_zip_code -> permanent_zip_code
 *   pi__attended_early_learning_program_name -> enrollments.attended_early_learning_program_name
 *   created-at  -> enrollments.created_at / updated_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_key')->nullable()->unique();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_house_number', 20)->nullable();
            $table->string('current_street_name', 100)->nullable();
            $table->string('current_barangay', 100)->nullable();
            $table->string('current_municipality', 100)->nullable();
            $table->string('current_province', 100)->nullable();
            $table->string('current_country', 100)->nullable();
            $table->string('current_zip_code', 10)->nullable();
            $table->string('current_address_status', 30)->nullable();
            $table->string('permanent_house_number', 20)->nullable();
            $table->string('permanent_street_name', 100)->nullable();
            $table->string('permanent_barangay', 100)->nullable();
            $table->string('permanent_municipality', 100)->nullable();
            $table->string('permanent_province', 100)->nullable();
            $table->string('permanent_country', 100)->nullable();
            $table->string('permanent_zip_code', 10)->nullable();
            $table->string('permanent_address_status', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('enrollment_parents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_key')->nullable()->unique();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('father_last_name', 50)->nullable();
            $table->string('father_first_name', 50)->nullable();
            $table->string('father_middle_name', 50)->nullable();
            $table->string('father_contact_number', 20)->nullable();
            $table->string('father_occupation', 100)->nullable();
            $table->string('father_relationship_status', 50)->nullable();
            $table->string('father_communication', 50)->nullable();

            $table->string('mother_last_name', 50)->nullable();
            $table->string('mother_first_name', 50)->nullable();
            $table->string('mother_middle_name', 50)->nullable();
            $table->string('mother_contact_number', 20)->nullable();
            $table->string('mother_occupation', 100)->nullable();
            $table->string('mother_relationship_status', 50)->nullable();
            $table->string('mother_communication', 50)->nullable();

            $table->string('guardian_last_name', 50)->nullable();
            $table->string('guardian_first_name', 50)->nullable();
            $table->string('guardian_middle_name', 50)->nullable();
            $table->string('guardian_contact_number', 20)->nullable();
            $table->string('guardian_occupation', 100)->nullable();
            $table->string('guardian_relationship_status', 50)->nullable();
            $table->string('guardian_communication', 50)->nullable();

            $table->string('emergency_contact', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('enrollment_medical', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_key')->nullable()->unique();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('allergy_medicine', 100)->nullable();
            $table->string('allergy_pollen', 100)->nullable();
            $table->string('allergy_food', 100)->nullable();
            $table->string('allergy_others', 100)->nullable();

            $table->json('medical_conditions')->nullable();
            $table->string('medical_conditions_others', 100)->nullable();

            $table->date('surgery_date')->nullable();
            $table->string('surgery_hospital', 100)->nullable();
            $table->string('surgery_body_part', 100)->nullable();

            $table->string('medication_type', 100)->nullable();
            $table->string('medication_dosage_schedule', 100)->nullable();

            $table->json('chronic_conditions')->nullable();
            $table->string('cancer_type', 100)->nullable();
            $table->string('chronic_conditions_others', 100)->nullable();

            $table->boolean('exposed_to_covid')->default(false);
            $table->string('pertinent_information', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('enrollment_special_needs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_key')->nullable()->unique();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();

            $table->json('diagnosis')->nullable();
            $table->string('shpcd_subtype', 20)->nullable();
            $table->string('vi_subtype', 20)->nullable();
            $table->json('manifestations')->nullable();
            $table->boolean('has_pwd_id')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_special_needs');
        Schema::dropIfExists('enrollment_medical');
        Schema::dropIfExists('enrollment_parents');
        Schema::dropIfExists('enrollment_addresses');
    }
};
