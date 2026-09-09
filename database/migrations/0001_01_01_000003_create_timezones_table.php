<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    // Up
    //
    // Creates the timezones table, keyed by IANA zone name. Skipped
    // entirely when config('locale.datasets.timezones') is off — see
    // Models\Country::timezones() for how that stays safe to call anyway.
    //
    // @return void
    //
    public function up(): void
    {
        if (! config('locale.datasets.timezones')) {
            return;
        }

        Schema::create(config('locale.table_names.timezones'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('abbreviation')->nullable();
            $table->integer('gmt_offset')->nullable();
            $table->string('gmt_offset_name')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }


    // Down
    //
    // @return void
    //
    public function down(): void
    {
        Schema::dropIfExists(config('locale.table_names.timezones'));
    }
};
