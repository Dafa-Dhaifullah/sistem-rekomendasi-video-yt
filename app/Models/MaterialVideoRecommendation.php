<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialVideoRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tahun',
        'kode_prodi',
        'kode_mata_kuliah',
        'kelas',
        'pertemuan',
        'video_id_1',
        'video_id_2',
        'video_id_3',
        'video_id_4',
        'video_id_5',
        'skor_kemiripan'
    ];

    // Mengubah tipe data JSON di database menjadi array secara otomatis di PHP
    protected $casts = [
        'skor_kemiripan' => 'array',
    ];
}