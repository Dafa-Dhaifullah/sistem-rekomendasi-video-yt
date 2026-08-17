<?php

namespace App\Services;

use App\Models\YoutubeVideoCache;
use App\Services\YoutubeApiService;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    protected $youtubeService;

    // Kumpulan kata-kata umum dan kata pengantar akademis yang tidak memiliki bobot teknis.
    // Kata-kata ini akan diabaikan (dibuang) saat proses preprocessing agar perhitungan TF-IDF lebih akurat.
    protected $stopwords = [
        'yang', 'di', 'ke', 'dari', 'pada', 'dalam', 'untuk', 'dengan',  
        'dan', 'atau', 'ini', 'itu', 'juga', 'sudah', 'saya', 'kita', 'kami',
        'adalah', 'merupakan', 'sebagai', 'hal', 'cara', 'cpmk', 'sub-cpmk', 'sub', 'indikator', 'penilaian', 'kriteria', 'bobot',
        'pertemuan', 'minggu', 'modul', 'bab', 'materi', 'tugas', 'kuis', 'uts', 'uas',
        'praktikum', 'teori', 'sks', 'mata', 'kuliah', 'matkul', 'kelas', 'silabus',
        'mahasiswa', 'dosen', 'pengajar', 'peserta', 'kelompok', 'individu',
        'mampu', 'menjelaskan', 'menganalisis', 'membandingkan', 'mengevaluasi', 
        'memformulasikan', 'memahami', 'mendeskripsikan', 'menyebutkan', 'mengidentifikasi', 
        'mengimplementasikan', 'menerapkan', 'merancang', 'mengembangkan', 'membuat', 
        'mengukur', 'menghitung', 'menyusun', 'menguraikan', 'menentukan', 'memilih',
        'metode', 'pembelajaran', 'studi', 'kasus', 'diskusi', 'ceramah', 'presentasi', 
        'latihan', 'referensi', 'pustaka', 'file', 'pendukung', 'instruksi', 'tujuan', 'l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7', 
        'l8', 'l9', 'l10', 'l11', 'l12', 'l13', 'l14',
        'masing', 'masing-masing', 'berdasarkan', 'ketepatan', 'tepat', 'tentang', 'antara', 'serta', 'kebutuhan'
    ];

    public function __construct(YoutubeApiService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Fungsi utama untuk menghasilkan rekomendasi video berdasarkan teks materi dosen.
     */
    public function generateRecommendationForMeeting($kodeProdi, $kelas, $kodeMatkul, $pertemuan, $judulMateri, $deskripsiMateri)
    {
        // Gabungkan judul dan deskripsi materi menjadi satu kesatuan string.
        $teksMateriUtuh = $judulMateri . ' ' . $deskripsiMateri;

        // Bersihkan teks materi dari huruf besar, tanda baca, angka, dan stopwords.
        $teksBersih = $this->preprocessText($teksMateriUtuh);

        // Pastikan $teksBersih berupa array untuk mencegah error pada implode().
        if (is_array($teksBersih)) {
            $arrayKataBersih = array_filter($teksBersih);
        } else {
            $arrayKataBersih = array_filter(explode(' ', trim($teksBersih)));
        }

        // Jika setelah dibersihkan teks materi ternyata kosong (hanya berisi stopwords), hentikan proses.
        if (empty($arrayKataBersih)) {
            return [];
        }

        // Ambil 5 kata pertama saja agar pencarian API YouTube sangat fokus dan relevan
        $keywordPencarian = implode(' ', array_slice($arrayKataBersih, 0, 5));

        // 1. Lakukan pencarian ke YouTube API menggunakan 5 kata kunci utama.
        // Fungsi ini juga akan otomatis menyimpan video hasil pencarian ke tabel database cache.
        $this->youtubeService->fetchAndCacheVideos($keywordPencarian, 15);

        // 2. Ambil korpus (semua video yang ada di database), limit korpus 60 untuk menghitung IDF.
        $semuaVideo = YoutubeVideoCache::orderBy('updated_at', 'desc')->limit(60)->get();
        if ($semuaVideo->isEmpty()) {  
            return []; 
        }

        // 3. Persiapkan semua dokumen untuk perhitungan IDF.
        // Teks materi dosen dimasukkan sebagai dokumen pertama (index 0).
        $materiBersih = $this->preprocessText($teksMateriUtuh);
        $kumpulanDokumen = [$materiBersih]; 
        
        $videoBersihArray = [];
        // Lakukan perulangan pada seluruh video YouTube dan bersihkan teksnya (Judul + Deskripsi).
        foreach ($semuaVideo as $video) {
            $teksVideo = $video->title . ' ' . $video->description;
            $bersih = $this->preprocessText($teksVideo);
            
            // Simpan hasil pembersihan teks video ke dalam array dengan key ID Video.
            $videoBersihArray[$video->video_id] = $bersih;
            // Tambahkan teks video yang sudah bersih ke dalam kumpulan dokumen korpus.
            $kumpulanDokumen[] = $bersih; 
        }

        // 4. Hitung nilai IDF berdasarkan seluruh dokumen (Materi + Seluruh Video Cache).
        $idfDictionary = $this->buildIdfDictionary($kumpulanDokumen);

        // 5. Hitung bobot TF-IDF untuk Teks Materi Dosen (Vektor A).
        $tfIdfMateri = $this->calculateTfIdf($materiBersih, $idfDictionary);

        $hasilRekomendasi = [];

        // 6. Hitung TF-IDF untuk tiap video (Vektor B_k) dan ukur Cosine Similarity-nya terhadap Materi Dosen (Vektor A).
        foreach ($videoBersihArray as $videoId => $videoBersih) {
            $tfIdfVideo = $this->calculateTfIdf($videoBersih, $idfDictionary);
            $skorKemiripan = $this->calculateCosineSimilarity($tfIdfMateri, $tfIdfVideo);

            // batas kemiripan 0.04.
            if ($skorKemiripan > 0.04) {
                $hasilRekomendasi[] = [
                    'youtube_id' => $videoId, 
                    'skor' => $skorKemiripan
                ];
            }
        }

        // Jika tidak ada video yang memenuhi ambang batas skor kemiripan, kembalikan array kosong.
        if (empty($hasilRekomendasi)) {
            return []; 
        }

        // 7. Urutkan hasil dari skor tertinggi ke skor terendah (Descending).
        usort($hasilRekomendasi, function ($a, $b) {
            return $b['skor'] <=> $a['skor'];
        });

        // 8. Ambil Top 5 Video teratas sebagai hasil rekomendasi final.
        return array_slice($hasilRekomendasi, 0, 5); 
    }

    /**
     * Membersihkan teks dari tanda baca, angka, kata hubung (stopwords), dan huruf besar (Case Folding).
     */
    private function preprocessText($text)
    {
        // Ubah semua huruf menjadi huruf kecil (Lowercasing).
        $text = strtolower($text);
        
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = explode(' ', $text);
        
        // Filter array kata dengan menghapus stopwords, string kosong, dan angka tunggal.
        $filteredWords = array_filter($words, function ($word) {
            return !in_array($word, $this->stopwords) && trim($word) !== '' && !is_numeric($word);
        });

        // Kembalikan array kata bersih tanpa index berlubang.
        return array_values($filteredWords); 
    }

    /**
     * Membangun kamus IDF dari seluruh dokumen di korpus (Materi Dosen + Seluruh Video Cache).
     * IDF mencari kata yang langka/unik. Kata yang sering muncul di banyak dokumen akan mendapat nilai IDF rendah.
     */
    private function buildIdfDictionary($semuaDokumen)
    {
        $totalDokumen = count($semuaDokumen);
        $documentFrequency = [];

        // Hitung Document Frequency (DF): Berapa banyak dokumen yang memuat suatu kata.
        foreach ($semuaDokumen as $dokumen) {
            $kataUnikDalamDokumen = array_unique($dokumen); // Ambil kata unik dalam 1 dokumen
            foreach ($kataUnikDalamDokumen as $kata) {
                if (!isset($documentFrequency[$kata])) {
                    $documentFrequency[$kata] = 0;
                }
                $documentFrequency[$kata]++; // Tambah hitungan jika kata tersebut ditemukan di dokumen ini
            }
        }

        $idf = [];
        // Kalkulasi logaritma basis 10 (Total Dokumen / Document Frequency).
        foreach ($documentFrequency as $kata => $df) {
            $idf[$kata] = log10($totalDokumen / $df);
        }

        return $idf;
    }

    /**
     * Menghitung nilai TF-IDF (Perkalian Term Frequency dengan Inverse Document Frequency) untuk sebuah array kata.
     */
    private function calculateTfIdf($arrayKata, $idfDictionary)
    {
        // Hitung frekuensi kemunculan tiap kata (TF).
        $termFrequency = array_count_values($arrayKata); 
        $tfIdf = [];

        // Kalikan nilai TF dengan nilai IDF dari kamus bersama.
        foreach ($termFrequency as $kata => $tf) {
            $nilaiIdf = $idfDictionary[$kata] ?? 0;
            $tfIdf[$kata] = $tf * $nilaiIdf;
        }

        return $tfIdf;
    }

    /**
     * Mengukur derajat kesamaan antar dua buah vektor TF-IDF.
     * Mengembalikan nilai antara 0 (sama sekali berbeda) hingga 1 (sangat identik).
     */
    private function calculateCosineSimilarity($vecA, $vecB)
    {
        $dotProduct = 0;
        $magA = 0;
        $magB = 0;

        // Kumpulkan semua kata kunci unik dari kedua vektor untuk diperbandingkan sejajar (dimensi vektor).
        $allKeys = array_unique(array_merge(array_keys($vecA), array_keys($vecB)));

        // Hitung Dot Product dan magnitudo (kuadrat dari masing-masing bobot).
        foreach ($allKeys as $key) {
            $valA = $vecA[$key] ?? 0;
            $valB = $vecB[$key] ?? 0;

            $dotProduct += ($valA * $valB); // Perkalian titik
            $magA += pow($valA, 2); // Kuadrat untuk Magnitudo A
            $magB += pow($valB, 2); // Kuadrat untuk Magnitudo B
        }

        // Akar kuadrat (Euclidean Norm) untuk menemukan panjang vektor sesungguhnya.
        $magA = sqrt($magA);
        $magB = sqrt($magB);

        // Cegah division by zero (pembagian dengan nol) jika teksnya benar-benar kosong.
        if ($magA * $magB == 0) {
            return 0; 
        }

        // Hasil akhir: Perkalian titik dibagi dengan hasil kali panjang vektor A dan B.
        return $dotProduct / ($magA * $magB);
    }
}