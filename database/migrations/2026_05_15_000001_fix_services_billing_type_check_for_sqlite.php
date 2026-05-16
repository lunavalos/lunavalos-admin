<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prior migration (2026_03_25_add_annual_to_billing_type_enum) skipped SQLite
 * using MODIFY COLUMN (MySQL-only syntax), so the CHECK constraint on services.billing_type
 * was never updated. SQLite requires a full table-recreation to modify a CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return; // MySQL/Postgres already handled by the prior migration.
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE "services_new" (
                "id"                       integer primary key autoincrement not null,
                "name"                     varchar not null,
                "description"              text,
                "price"                    numeric not null default \'0\',
                "billing_type"             varchar check ("billing_type" in (\'unique\', \'monthly\', \'annual\')) not null default \'unique\',
                "created_at"               datetime,
                "updated_at"               datetime,
                "is_package"               tinyint(1) not null default \'0\',
                "renewal_price"            numeric not null default \'0\',
                "required_addon_category"  varchar,
                "payment_plan_months"      integer not null default \'1\'
            )
        ');

        DB::statement('
            INSERT INTO "services_new"
                ("id","name","description","price","billing_type","created_at","updated_at",
                 "is_package","renewal_price","required_addon_category","payment_plan_months")
            SELECT
                "id","name","description","price","billing_type","created_at","updated_at",
                "is_package","renewal_price","required_addon_category","payment_plan_months"
            FROM "services"
        ');

        DB::statement('DROP TABLE "services"');
        DB::statement('ALTER TABLE "services_new" RENAME TO "services"');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE "services_old_rollback" (
                "id"                       integer primary key autoincrement not null,
                "name"                     varchar not null,
                "description"              text,
                "price"                    numeric not null default \'0\',
                "billing_type"             varchar check ("billing_type" in (\'unique\', \'monthly\')) not null default \'unique\',
                "created_at"               datetime,
                "updated_at"               datetime,
                "is_package"               tinyint(1) not null default \'0\',
                "renewal_price"            numeric not null default \'0\',
                "required_addon_category"  varchar,
                "payment_plan_months"      integer not null default \'1\'
            )
        ');

        DB::statement('
            INSERT INTO "services_old_rollback"
                ("id","name","description","price","billing_type","created_at","updated_at",
                 "is_package","renewal_price","required_addon_category","payment_plan_months")
            SELECT
                "id","name","description","price",
                CASE WHEN "billing_type" = \'annual\' THEN \'unique\' ELSE "billing_type" END,
                "created_at","updated_at","is_package","renewal_price",
                "required_addon_category","payment_plan_months"
            FROM "services"
        ');

        DB::statement('DROP TABLE "services"');
        DB::statement('ALTER TABLE "services_old_rollback" RENAME TO "services"');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
