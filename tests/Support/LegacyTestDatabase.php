<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A throwaway SQLite database that stands in for the old AMS MySQL database.
 * Each test gets a fresh file so the importer can be run repeatedly.
 */
final class LegacyTestDatabase
{
    private static ?string $path = null;

    public static function configure(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gems-legacy-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary legacy database.');
        }

        self::$path = $path;

        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ],
        ]);

        DB::purge('legacy');
    }

    public static function cleanup(): void
    {
        DB::purge('legacy');

        if (self::$path !== null && is_file(self::$path)) {
            @unlink(self::$path);
        }

        self::$path = null;
    }

    public static function createTables(): void
    {
        $connection = DB::connection('legacy');

        foreach (self::schemas() as $table => $columns) {
            $definitions = array_map(
                fn (string $column): string => '"'.$column.'" text',
                $columns,
            );

            $connection->statement('CREATE TABLE "'.$table.'" ('.implode(', ', $definitions).')');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function insert(string $table, array $row): void
    {
        DB::connection('legacy')->table($table)->insert($row);
    }

    /**
     * Columns read by the importer. SQLite is loosely typed, so everything is
     * stored as text and the importer's cleaners do the casting.
     *
     * @return array<string, list<string>>
     */
    private static function schemas(): array
    {
        $person = [
            'last_name', 'first_name', 'middle_name', 'contact_number',
            'occupation', 'relationship_status', 'communication',
        ];

        return [
            'mother_tongue' => ['id', 'name'],
            'religion' => ['id', 'name'],
            'indigenous_group' => ['id', 'name'],
            'user_account' => ['id', 'username', 'password_hash', 'role', 'email', 'created_at', 'is_active'],
            'enrollment2' => [
                'fk_full_name_bd', 'ed_grade_level', 'ed_lrn', 'ed_school_year',
                'rl_last_grade_level_completed', 'rl_last_school_year_completed',
                'rl_school_attended', 'rl_school_id', 'pi_psa_bcn', 'pi_last_name',
                'pi_first_name', 'pi_middle_name', 'pi_extension', 'pi_birth_date',
                'pi_sex', 'pi_place_of_birth', 'pi_mother_tongue_id', 'pi_religion_id',
                'pi__attended_early_learning_program_name', 'pi_learning_classification',
                'ac_indigenous_group_id', 'ac_4ps_household_number', 'user_account_id',
                'li_learning_modality', 'verification', 'section', 'created-at',
            ],
            'enrollment_address2' => [
                'id', 'fk_full_name_bd',
                'ca_house_number', 'ca_street_name', 'ca_barangay', 'ca_municipality',
                'ca_provice', 'ca_country', 'ca_zipcode', 'ca_address_status',
                'pa_house_number', 'pa_street_name', 'pa_barangay', 'pa_municipality',
                'pa_province', 'pa_country', 'pa_zip_code', 'pa_address_status',
            ],
            'enrollment_parent2' => array_merge(
                ['id', 'fk_full_name_bd'],
                array_map(fn (string $column): string => 'fi_'.$column, $person),
                array_map(fn (string $column): string => 'mi_'.$column, $person),
                array_map(fn (string $column): string => 'gi_'.$column, $person),
                ['ec_to_contact'],
            ),
            'enrollment_medical2' => [
                'id', 'fk_full_name_bd',
                'mf_a_medicine', 'mf_a_pollen', 'mf_a_food', 'mf_a_others',
                'mf_o_medical_conditions', 'mf_o_others', 'mf_sh_surgery_date',
                'mf_sh_hospital_name', 'mf_sh_bodypart_affected', 'mf_tm_type',
                'mf_tm_dosage_schedule', 'mf_mc_conditions', 'mf_mc_cancer_type',
                'mf_mc_others', 'mf_exposure_c_v', 'mf_o_pertinent_information',
            ],
            'enrollment_special_needs2' => [
                'id', 'fk_full_name_bd', 'snep_a1_diagnosis', 'snep_a1_sub_shpcd',
                'snep_a1_sub_vi', 'snep_a2_manifestations', 'snep_pwd_id',
            ],
        ];
    }
}
