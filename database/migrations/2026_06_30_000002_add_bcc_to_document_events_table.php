<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_events', function (Blueprint $table) {
            $table->string('bcc')->nullable()->after('cc');
        });
    }

    public function down(): void
    {
        Schema::table('document_events', function (Blueprint $table) {
            $table->dropColumn('bcc');
        });
    }
};
