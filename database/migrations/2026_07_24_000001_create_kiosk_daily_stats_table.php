<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('kiosk_id', 64);
            $table->string('metric', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'kiosk_id', 'metric']);
            $table->index(['stat_date', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_daily_stats');
    }
};
