<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Device only (platform.md §4, §7): one row per audio file the phone
     * has tried to download, keyed by its object key.
     */
    public function up(): void
    {
        Schema::create('media_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('status');
            $table->unsignedBigInteger('bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_downloads');
    }
};
