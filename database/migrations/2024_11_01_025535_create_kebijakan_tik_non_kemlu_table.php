<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kebijakan_tik_non_kemlu', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kebijakan');
            $table->string('nomor_kebijakan');
            $table->year('tahun_penerbitan');
            $table->text('perihal');
            $table->string('instansi');
            $table->string('tautan')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kebijakan_tik_non_kemlu');
    }
};