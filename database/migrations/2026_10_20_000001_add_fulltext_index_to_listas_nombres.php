<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `tbListasNegrasUIF` ADD FULLTEXT INDEX `ft_tbListasNegrasUIF_Nombre` (`Nombre`)');
        DB::statement('ALTER TABLE `tbListasNegraCNSF` ADD FULLTEXT INDEX `ft_tbListasNegraCNSF_Nombre` (`Nombre`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `tbListasNegrasUIF` DROP INDEX `ft_tbListasNegrasUIF_Nombre`');
        DB::statement('ALTER TABLE `tbListasNegraCNSF` DROP INDEX `ft_tbListasNegraCNSF_Nombre`');
    }
};
