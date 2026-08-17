<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Sistem Rekomendasi Edukasi</title>
    <!-- Memanggil Bootstrap dari CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4 px-5">
        <h2 class="text-center mb-4">Demo Sistem Rekomendasi Video (TF-IDF & Cosine Similarity)</h2>
        
        <!-- Notifikasi -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <div class="row">
            <!-- Bagian Kiri: Form Input -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Input Materi Baru</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('demo.process') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Kode Mata Kuliah</label>
                                <input type="text" name="kode_mata_kuliah" class="form-control" placeholder="Contoh: IF572" required>
                            </div>
                           
<div class="form-group mb-3">
    <label for="kode_prodi">Kode Prodi</label>
    <input type="text" name="kode_prodi" id="kode_prodi" class="form-control" placeholder="Contoh: IF" required>
</div>

<div class="form-group mb-3">
    <label for="kelas">Kelas</label>
    <input type="text" name="kelas" id="kelas" class="form-control" placeholder="Contoh: IFPPL8391-B" required>
</div>
                            <div class="mb-3">
                                <label class="form-label">Pertemuan Ke- (1-14)</label>
                                <input type="number" name="pertemuan" class="form-control" min="1" max="14" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Judul Materi</label>
                                <input type="text" name="judul_materi" class="form-control" placeholder="Contoh: Pengantar Basis Data" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi / Penjelasan Materi</label>
                                <textarea name="deskripsi_materi" class="form-control" rows="8" placeholder="Ketik atau paste deskripsi materi di sini..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Cari Rekomendasi!</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bagian Kanan: Riwayat Rekomendasi dengan Embed Video -->
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Hasil Rekomendasi Video</h5>
                        <span class="badge bg-secondary">Top 5 Berdasarkan Skor Kemiripan</span>
                    </div>
                    <div class="card-body bg-light">
                        @if($riwayatRekomendasi->isEmpty())
                            <p class="text-muted text-center my-4">Belum ada data rekomendasi yang diproses.</p>
                        @else
                            <!-- Menggunakan Accordion agar UI tidak memanjang ke bawah -->
                            <div class="accordion" id="accordionRiwayat">
                                @foreach($riwayatRekomendasi as $index => $riwayat)
                                    <div class="accordion-item mb-3 shadow-sm border-0">
                                        <h2 class="accordion-header" id="heading{{ $index }}">
                                            <button class="accordion-button {{ $index == 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}" aria-expanded="{{ $index == 0 ? 'true' : 'false' }}">
                                                <strong>Mata Kuliah: {{ $riwayat->kode_mata_kuliah }} - Pertemuan {{ $riwayat->pertemuan }}</strong>
                                            </button>
                                        </h2>
                                        <div id="collapse{{ $index }}" class="accordion-collapse collapse {{ $index == 0 ? 'show' : '' }}" data-bs-parent="#accordionRiwayat">
                                            <div class="accordion-body bg-white">
                                                
                                                @php
                                                    // Membaca array JSON skor kemiripan dari database
                                                    $skorData = is_string($riwayat->skor_kemiripan) ? json_decode($riwayat->skor_kemiripan, true) : $riwayat->skor_kemiripan;
                                                    
                                                    $videoIds = [
                                                        $riwayat->video_id_1,
                                                        $riwayat->video_id_2,
                                                        $riwayat->video_id_3,
                                                        $riwayat->video_id_4,
                                                        $riwayat->video_id_5,
                                                    ];
                                                @endphp

                                                <div class="row">
                                                    @foreach($videoIds as $urutan => $vidId)
                                                        @if($vidId)
                                                            @php
                                                                // Mengambil judul video langsung dari tabel cache
                                                                $videoDetail = \App\Models\YoutubeVideoCache::where('video_id', $vidId)->first();
                                                                $judulVideo = $videoDetail ? $videoDetail->title : 'Judul tidak tersedia (Telah dihapus/tidak tercache)';
                                                                
                                                                // Mencari skor cosine similarity untuk video ID ini
                                                                $skorSimilarity = 0;
                                                                if (is_array($skorData)) {
                                                                    foreach ($skorData as $item) {
                                                                        if (isset($item['youtube_id']) && $item['youtube_id'] == $vidId) {
                                                                            $skorSimilarity = $item['skor'];
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            
                                                            <div class="col-md-6 col-lg-4 mb-4">
                                                                <div class="card h-100 border-primary">
                                                                    <div class="card-header py-2 px-3 bg-primary text-white d-flex justify-content-between align-items-center">
                                                                        <span class="fw-bold">Top {{ $urutan + 1 }}</span>
                                                                        <span class="badge bg-light text-primary fs-6" title="Skor Cosine Similarity">
                                                                            Skor: {{ number_format($skorSimilarity, 4) }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="card-body p-3">
                                                                        <!-- Judul Video -->
                                                                        <h6 class="card-title text-truncate fw-bold mb-3" title="{{ $judulVideo }}">
                                                                            {{ $judulVideo }}
                                                                        </h6>
                                                                        
                                                                        <!-- Embed YouTube Menggunakan Iframe -->
                                                                        <!-- Iframe ini secara otomatis akan memuat Thumbnail resmi dari YouTube sebelum tombol Play ditekan -->
                                                                        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
                                                                            <iframe 
                                                                                src="https://www.youtube.com/embed/{{ $vidId }}" 
                                                                                title="{{ $judulVideo }}" 
                                                                                frameborder="0" 
                                                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                                                allowfullscreen>
                                                                            </iframe>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div> <!-- End Row Grid Video -->

                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div> <!-- End Accordion -->
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Script Bootstrap untuk interaksi Accordion -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>