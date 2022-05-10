<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('telegram_user_id');
            $table->boolean('is_registered')->default(false);
            $table->integer('step')->nullable();
            $table->string('msisdn')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->text('full_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nickname')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
