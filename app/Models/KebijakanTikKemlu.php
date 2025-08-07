<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KebijakanTikKemlu extends Model
{
    protected $table = 'kebijakan_tik_kemlu';
    
    protected $fillable = [
        'jenis_kebijakan',
        'nomor_kebijakan',
        'tahun_penerbitan',
        'perihal_kebijakan',
        'tautan'
    ];
    protected $casts = [
        'tahun_penerbitan' => 'integer',
    ];
}
