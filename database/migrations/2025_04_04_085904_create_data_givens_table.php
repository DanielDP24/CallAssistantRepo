<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('data_givens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('name_given');
            $table->string('email_given');
            $table->string('company_given');
            $table->string('callerNum');
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_givens');
    }
};
