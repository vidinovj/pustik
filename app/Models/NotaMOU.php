<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaMOU extends Model
{
    use HasFactory;

    // Nama tabel sesuai dengan database Anda
    protected $table = 'nota_kesepahaman';

    // Field yang dapat diisi
    protected $fillable = [
        'jenis_dokumen',
        'perihal_dokumen',
        'satker_kemlu_terkait',
        'kl_external_terkait',
        'tanggal_disahkan',
        'tanggal_berakhir',
        'status',
        'keterangan',
    ];

    // Casting kolom ke tipe data tertentu
    protected $casts = [
        'tanggal_disahkan' => 'date',
        'tanggal_berakhir' => 'date',
    ];
}
