<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KebijakanNonKemlu extends Model
{
    use HasFactory;

    protected $table = 'kebijakan_tik_non_kemlu';

    protected $fillable = [
        'jenis_kebijakan',
        'nomor_kebijakan',
        'tahun_penerbitan',
        'perihal_kebijakan',
        'instansi',
        'tautan'
    ];
}
