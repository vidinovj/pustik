<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KebijakanTikNonKemlu extends Model
{
    protected $table = 'kebijakan_tik_non_kemlu';
    
    protected $fillable = [
        'jenis_kebijakan',
        'nomor_kebijakan',
        'tahun_penerbitan',
        'perihal',
        'instansi',
        'tautan'
    ];
    protected $casts = [
        'tahun_penerbitan' => 'integer',
    ];
}
