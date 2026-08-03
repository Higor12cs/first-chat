<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION uuid_larger(uuid, uuid) RETURNS uuid
                LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
                AS 'SELECT CASE WHEN $1 > $2 THEN $1 ELSE $2 END';
        SQL);

        DB::statement('DROP AGGREGATE IF EXISTS max(uuid);');

        DB::statement(<<<'SQL'
            CREATE AGGREGATE max(uuid) (
                SFUNC = uuid_larger,
                STYPE = uuid,
                COMBINEFUNC = uuid_larger,
                PARALLEL = SAFE,
                SORTOP = >
            );
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP AGGREGATE IF EXISTS max(uuid);');
        DB::statement('DROP FUNCTION IF EXISTS uuid_larger(uuid, uuid);');
    }
};
