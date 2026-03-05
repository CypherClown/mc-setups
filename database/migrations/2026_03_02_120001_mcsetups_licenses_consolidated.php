<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Consolidated: create mcsetups_licenses (global, S3 fields), seed if empty, allow string file_id in egg.
     */
    public function up(): void
    {
        if (!Schema::hasTable('mcsetups_licenses')) {
            Schema::create('mcsetups_licenses', function (Blueprint $table) {
                $table->id();
                $table->string('license_key')->unique();
                $table->string('store_url')->nullable();
                $table->string('s3_endpoint', 512)->nullable();
                $table->string('s3_access_key', 512)->nullable();
                $table->string('s3_secret_key', 512)->nullable();
                $table->string('s3_bucket', 255)->nullable();
                $table->string('s3_region', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        } else {
            $this->migrateExistingTable();
        }

        $existingLicense = DB::table('mcsetups_licenses')->first();
        if (!$existingLicense) {
            DB::table('mcsetups_licenses')->insert([
                'license_key' => 'Unable to acquire a license key automatically, please contact the creator directly.',
                'store_url' => 'https://mcapi.hxdev.org',
                'is_active' => true,
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $eggId = DB::table('eggs')
            ->where('name', 'MCSetups Installer')
            ->where('author', 'support@hxdev.org')
            ->value('id');

        if ($eggId !== null) {
            DB::table('egg_variables')
                ->where('egg_id', $eggId)
                ->where('env_variable', 'MCSETUPS_FILE_ID')
                ->update(['rules' => 'required|string']);
        }
    }

    protected function migrateExistingTable(): void
    {
        if (Schema::hasColumn('mcsetups_licenses', 'server_uuid')) {
            try {
                $result = DB::selectOne("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'mcsetups_licenses'
                    AND COLUMN_NAME = 'server_uuid'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    LIMIT 1
                ");
                if ($result?->CONSTRAINT_NAME) {
                    Schema::table('mcsetups_licenses', fn (Blueprint $t) => $t->dropForeign($result->CONSTRAINT_NAME));
                }
            } catch (\Exception $e) {
            }
            Schema::table('mcsetups_licenses', function (Blueprint $table) {
                $table->dropIndex(['server_uuid']);
                $table->dropColumn('server_uuid');
            });
        }

        if (Schema::hasColumn('mcsetups_licenses', 'admin_api_key')) {
            Schema::table('mcsetups_licenses', fn (Blueprint $t) => $t->dropColumn('admin_api_key'));
        }

        foreach (
            [
                ['s3_endpoint', 512, 'store_url'],
                ['s3_access_key', 512, 's3_endpoint'],
                ['s3_secret_key', 512, 's3_access_key'],
                ['s3_bucket', 255, 's3_secret_key'],
                ['s3_region', 64, 's3_bucket'],
            ] as [$col, $len, $after]
        ) {
            if (!Schema::hasColumn('mcsetups_licenses', $col)) {
                Schema::table('mcsetups_licenses', fn (Blueprint $t) => $t->string($col, $len)->nullable()->after($after));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcsetups_licenses');

        $eggId = DB::table('eggs')
            ->where('name', 'MCSetups Installer')
            ->where('author', 'support@hxdev.org')
            ->value('id');

        if ($eggId !== null) {
            DB::table('egg_variables')
                ->where('egg_id', $eggId)
                ->where('env_variable', 'MCSETUPS_FILE_ID')
                ->where('rules', 'required|string')
                ->update(['rules' => 'required|numeric']);
        }
    }
};
