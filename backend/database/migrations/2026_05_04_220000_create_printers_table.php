<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Alpha-1
            $table->string('model'); // e.g. Ender 3 V2
            $table->string('technology'); // FDM or SLA
            $table->string('status')->default('idle'); // idle, printing, maintenance
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('printer_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['printer_id']);
            $table->dropColumn('printer_id');
        });
        Schema::dropIfExists('printers');
    }
};
