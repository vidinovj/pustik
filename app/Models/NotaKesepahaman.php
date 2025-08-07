<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaKesepahaman extends Model
{
    protected $table = 'nota_kesepahaman';
    
    protected $fillable = [
        'jenis_dokumen',
        'perihal_dokumen',
        'satker_kemlu_terkait',
        'kl_external_terkait',
        'tanggal_disahkan',
        'tanggal_berakhir',
        'status',
        'keterangan'
    ];
    protected $casts = [
        'tanggal_disahkan' => 'date',
        'tanggal_berakhir' => 'date',
    ];
}
