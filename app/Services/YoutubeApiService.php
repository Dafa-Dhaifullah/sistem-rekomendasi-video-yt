<?php

namespace App\Services;

use App\Models\YoutubeVideoCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service ini khusus menangani komunikasi antara aplikasi lokal dengan YouTube Data API v3.
 * Bertugas mencari video terkait materi pembelajaran dan menyimpannya (caching) ke database lokal
 * untuk mencegah request berulang yang bisa menyebabkan limit API habis (Rate Limiting).
 */
class YoutubeApiService
{
    // Menyimpan kredensial API Key yang didapatkan dari Google Cloud Console
    protected $apiKey;
    
    // URL dasar (Base URL) untuk seluruh request ke YouTube Data API v3
    protected $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        // Mengambil API Key dari file .env (variabel YOUTUBE_API_KEY)
        $this->apiKey = env('YOUTUBE_API_KEY');
    }

    /**
     * Mencari video di YouTube berdasarkan kata kunci materi 
     * dan menyimpannya ke dalam database (cache).
     *
     * @param string $keyword Kata kunci pencarian (berasal dari teks materi yang sudah di-preprocess)
     * @param int $maxResults Jumlah maksimal video yang ingin ditarik dalam satu kali request
     * @return array Mengembalikan kumpulan data video yang berhasil disimpan/diperbarui
     */
    public function fetchAndCacheVideos($keyword, $maxResults = 10)
    {
        try {
            // Melakukan HTTP GET Request ke endpoint pencarian YouTube (/search)
            $response = Http::get("{$this->baseUrl}/search", [
                'key'               => $this->apiKey,      // Kunci autentikasi akses API
                'q'                 => $keyword,           // String kata kunci yang akan dicari di YouTube
                'part'              => 'snippet',          // Meminta kembalian data berupa 'snippet' (judul, deskripsi, thumbnail)
                'type'              => 'video',            // Memastikan hasil yang dikembalikan HANYA video (bukan channel atau playlist)
                'maxResults'        => $maxResults,        // Batas maksimal hasil pencarian
                'relevanceLanguage' => 'id',               // Memprioritaskan algoritma YouTube untuk mencari video berbahasa Indonesia
            ]);

            // Mengecek apakah respons HTTP dari server Google berstatus 200 OK (Berhasil)
            if ($response->successful()) {
                // Ekstrak array 'items' dari JSON response. Jika kosong, kembalikan array kosong.
                $videos = $response->json()['items'] ?? [];
                $cachedVideos = [];

                // Lakukan perulangan untuk mengekstrak dan menyimpan setiap video ke database
                foreach ($videos as $video) {
                    $videoId = $video['id']['videoId'];
                    $snippet = $video['snippet'];

                    // Mekanisme UPSERT (Update or Insert) menggunakan Eloquent updateOrCreate.
                    // Logika: Cari berdasarkan 'video_id'. Jika sudah ada, perbarui judul/deskripsinya. 
                    // Jika belum ada sama sekali, buat baris data baru.
                    $cachedVideo = YoutubeVideoCache::updateOrCreate(
                        ['video_id' => $videoId], // Kondisi pencarian (Unique Key)
                        [ // Data yang akan diisi atau diperbarui
                            'title'         => $snippet['title'],
                            'description'   => $snippet['description'],
                            // Gunakan null coalescing (??) untuk mencegah error jika resolusi thumbnail 'high' tidak tersedia dari YouTube
                            'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? null,
                        ]
                    );

                    // Masukkan objek model yang berhasil disimpan ke dalam array untuk direturn ke RecommendationService
                    $cachedVideos[] = $cachedVideo;
                }

                return $cachedVideos;
            }

            // Jika respons Google BUKAN 200 OK,
            // catat pesan error mentah dari YouTube ke file log (storage/logs/laravel.log) agar mudah di-debug.
            Log::error('YouTube API Error: ' . $response->body());
            return [];

        } catch (\Exception $e) {
            // Blok catch ini akan menangkap error fatal pada sistem/jaringan 
            // Error dicatat ke file log agar aplikasi tidak hancur (crash) saat dijalankan oleh user.
            Log::error('YouTube API Exception: ' . $e->getMessage());
            return [];
        }
    }
}