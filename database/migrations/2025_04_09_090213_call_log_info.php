<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('CallLogInfo', function (Blueprint $table) {
            $table->id();
            $table->string('callSid');
            $table->string('name')->nullable();
            $table->string('name_given')->nullable();
            $table->string('name_accuracy')->nullable();
            $table->string('email')->nullable();
            $table->string('email_given')->nullable();
            $table->string('email_accuracy')->nullable();
            $table->string('email_IA_confidence')->nullable();
            $table->string('company')->nullable();
            $table->string('company_given')->nullable();
            $table->string('company_accuracy')->nullable();
            $table->string('total_accuracy')->nullable();
            $table->string('callerNum')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CallLogInfo');
    }
};
