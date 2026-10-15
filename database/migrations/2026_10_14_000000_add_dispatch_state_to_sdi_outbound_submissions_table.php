<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdi_outbound_submissions', function (Blueprint $table) {
            $table->timestamp('dispatch_started_at')->nullable()->after('business_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('sdi_outbound_submissions', function (Blueprint $table) {
            $table->dropColumn('dispatch_started_at');
        });
    }
};
