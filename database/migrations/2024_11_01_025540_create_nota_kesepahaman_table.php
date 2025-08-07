<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('nota_kesepahaman', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis_dokumen', ['MOU', 'PKS']);
            $table->text('perihal_dokumen');
            $table->string('satker_kemlu_terkait');
            $table->string('kl_external_terkait');
            $table->date('tanggal_disahkan');
            $table->date('tanggal_berakhir');
            $table->enum('status', ['Aktif', 'Tidak Aktif', 'Dalam Perpanjangan']);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('nota_kesepahaman');
    }
};