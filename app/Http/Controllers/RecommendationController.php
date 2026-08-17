<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;
use App\Models\MaterialVideoRecommendation;
use Illuminate\Support\Facades\Log; 

class RecommendationController extends Controller
{
    // Meng-inject RecommendationService agar logika TF-IDF terpisah dari Controller
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Menampilkan halaman UI Demo untuk keperluan presentasi.
     * Mengembalikan view yang berisi form input materi dan tabel riwayat rekomendasi.
     */
    public function index()
    {
        // Mengambil semua riwayat rekomendasi yang sudah pernah dihitung sebelumnya, diurutkan dari yang terbaru
        $riwayatRekomendasi = MaterialVideoRecommendation::orderBy('created_at', 'desc')->get();
        
        // Melemparkan variabel $riwayatRekomendasi ke file view 'demo.index.blade.php'
        return view('demo.index', compact('riwayatRekomendasi'));
    }

    /**
     * Endpoint API utama (Trigger) untuk menjalankan komputasi TF-IDF & Cosine Similarity.
     * Dipanggil saat dosen menekan tombol simpan materi di LMS, atau via tombol submit di UI Demo.
     */
    public function process(Request $request)
    {
        // 1. Validasi masukan data dari request. 
        // Menyimpan data yang lolos validasi ke variabel $validated agar lebih aman dan mudah dipanggil.
        $validated = $request->validate([
            'kode_prodi'       => 'required|string',
            'kelas'            => 'required|string',
            'kode_mata_kuliah' => 'required|string',
            'pertemuan'        => 'required|integer|min:1|max:14', 
            'judul_materi'     => 'required|string|max:255',
            'deskripsi_materi' => 'required|string',
        ]);

        try {
            // 2. Lempar data yang sudah tervalidasi ke Service untuk diproses algoritma TF-IDF.
            // Hasil $top5 akan berisi array maksimal 5 video (berupa ID dan skor kemiripan), atau array kosong [].
            $top5 = $this->recommendationService->generateRecommendationForMeeting(
                $validated['kode_prodi'],
                $validated['kelas'],
                $validated['kode_mata_kuliah'],
                $validated['pertemuan'],
                $validated['judul_materi'],
                $validated['deskripsi_materi']
            );

            // 3. Siapkan pemetaan data untuk disimpan ke dalam kolom-kolom tabel database.
            // Menggunakan null coalescing operator (?? null) agar field terisi NULL jika video ke-n tidak ditemukan/kurang dari 5.
            $dataVideo = [
                'tahun'          => date('Y'), // Mencatat tahun akademik saat ini
                'skor_kemiripan' => $top5,     // Disimpan dalam kolom ber-tipe JSON di database
                'video_id_1'     => $top5[0]['youtube_id'] ?? null,
                'video_id_2'     => $top5[1]['youtube_id'] ?? null,
                'video_id_3'     => $top5[2]['youtube_id'] ?? null,
                'video_id_4'     => $top5[3]['youtube_id'] ?? null,
                'video_id_5'     => $top5[4]['youtube_id'] ?? null,
            ];

            // 4. Mekanisme UPSERT (Update or Insert) untuk menyimpan hasil akhir rekomendasi.
            // Jika untuk kode_mata_kuliah, pertemuan, & kelas yang sama sudah pernah dikalkulasi, data lama akan ditimpa (di-update).
            // Jika belum ada, sistem akan membuat baris record baru. 
            // Data tetap disimpan sebagai jejak riwayat meskipun array $top5 bernilai kosong (semua video_id = NULL).
            $recommendation = MaterialVideoRecommendation::updateOrCreate(
                [ // Kondisi Pencarian (Unique Identifier)
                    'kode_prodi'       => $validated['kode_prodi'],
                    'kelas'            => $validated['kelas'],
                    'kode_mata_kuliah' => $validated['kode_mata_kuliah'],
                    'pertemuan'        => $validated['pertemuan'],
                ],
                $dataVideo // Nilai yang akan diperbarui/di-insert
            );

            // 5. Catat ke file log sistem jika algoritma gagal menemukan video yang memenuhi ambang batas relevansi (> 0.04).
            if (empty($top5)) {
                Log::info("Rekomendasi Kosong: Tidak ada video relevan untuk Matkul {$validated['kode_mata_kuliah']} Pertemuan {$validated['pertemuan']} Kelas {$validated['kelas']}");
            }

            // 6. Siapkan pesan respons dinamis berdasarkan keberhasilan algoritma menemukan video.
            $pesan = empty($top5) 
                ? 'Materi berhasil diproses, namun tidak ada video YouTube yang relevan.' 
                : 'Rekomendasi berhasil dikalkulasi!';

            // 7a. Penanganan Respons untuk HTTP Browser (dipicu dari UI Demo/Form HTML).
            if (!$request->wantsJson()) {
                // Gunakan notifikasi 'warning' (warna kuning) jika video kosong, dan 'success' (hijau) jika berhasil menemukan video.
                $tipeNotif = empty($top5) ? 'warning' : 'success';
                // Kembali ke halaman sebelumnya (UI Demo) sambil membawa session flash message.
                return redirect()->back()->with($tipeNotif, $pesan);
            }
            
            // 7b. Penanganan Respons untuk Request API (dipicu dari Postman atau integrasi LMS).
            return response()->json([
                'status'  => 'success',
                'message' => $pesan,
                'data'    => [
                    'kode_mata_kuliah' => $validated['kode_mata_kuliah'],
                    'pertemuan'        => $validated['pertemuan'],
                    'kelas'            => $validated['kelas'],
                    'videos'           => $top5 // Akan berisi array data video atau array kosong []
                ]
            ], 200);

        } catch (\Exception $e) {
            // 8. Blok penanganan error. Akan terpanggil jika terjadi error pada database, syntax, atau YouTube API.
            Log::error("Error Recommendation API: " . $e->getMessage());

            // Respons jika request datang dari Browser (UI Demo)
            if (!$request->wantsJson()) {
                return redirect()->back()->with('error', 'Gagal memproses rekomendasi. Pastikan koneksi internet stabil atau cek kuota API YouTube.');
            }

            // Respons format JSON jika request datang dari API/LMS
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memproses rekomendasi',
                'error'   => $e->getMessage() 
            ], 500); 
        }
    }

    /**
     * Endpoint API Mode Baca (Read-Only) yang dipanggil oleh halaman depan LMS.
     * Berfungsi mengembalikan data video yang sudah matang dari database dengan sangat cepat tanpa perlu komputasi ulang.
     */
    public function getRecommendation(Request $request)
    {
        // 1. Validasi parameter query pencarian
        $request->validate([
            'kode_mata_kuliah' => 'required|string',
            'pertemuan'        => 'required|integer',
            'kelas'            => 'required|string',
        ]);

        // 2. Mencari satu baris data (first) yang cocok dengan parameter di tabel MaterialVideoRecommendation
        $data = MaterialVideoRecommendation::where('kode_mata_kuliah', $request->kode_mata_kuliah)
            ->where('pertemuan', $request->pertemuan)
            ->where('kelas', $request->kelas)
            ->first();

        // 3. Jika data tidak ditemukan (dosen belum mengunggah materi atau belum men-trigger komputasi)
        if (!$data) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Rekomendasi video belum dikalkulasi untuk materi ini.'
            ], 404); 
        }

        // 4. Jika data ditemukan, kembalikan dalam format JSON yang terstruktur rapi.
        return response()->json([
            'status'  => 'success',
            'data'    => [
                'kode_mata_kuliah' => $data->kode_mata_kuliah,
                'pertemuan'        => $data->pertemuan,
                'kelas'            => $data->kelas,
                'skor_kemiripan'   => $data->skor_kemiripan, // Array JSON berisi gabungan ID & Skor
                // Mengambil nilai ID video satu per satu dari masing-masing kolom database.
                // array_filter() berfungsi untuk membuang elemen bernilai null (contoh: jika video rekomendasi cuma ada 3).
                'video_ids'        => array_filter([
                    $data->video_id_1,
                    $data->video_id_2,
                    $data->video_id_3,
                    $data->video_id_4,
                    $data->video_id_5,
                ])
            ]
        ], 200); 
    }
}