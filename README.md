# Dokumentasi Integrasi API Sistem Rekomendasi Video Edukasi E-Learning ITG

Dokumen ini berisi spesifikasi teknis dan panduan integrasi sistem rekomendasi materi perkuliahan berbasis *Content-Based Filtering* (TF-IDF & Cosine Similarity) dengan platform LMS E-Learning ITG.

---

## 1. Ikhtisar Arsitektur

Sistem rekomendasi dirancang sebagai Microservice/API Terpisah menggunakan arsitektur *Decoupled*. Komunikasi antar-server (*Server-to-Server / S2S*) dibagi menjadi 2 fase utama:

1. **Fase Asynchronous Trigger (`POST /api/process`)**: Dipanggil oleh LMS saat dosen menambah atau memperbarui materi. Server API Rekomendasi akan mengeksekusi *preprocessing*, pemanggilan YouTube API, kalkulasi TF-IDF & Cosine Similarity, lalu menyimpan hasil Top 5 video ke database lokal.
2. **Fase Read-Only Retrieval (`GET /api/rekomendasi`)**: Dipanggil oleh LMS saat mahasiswa/dosen membuka halaman materi. Server API Rekomendasi mengembalikan data video yang sudah matang di database.

---

## 2. Autentikasi

Setiap request yang dikirim dari server LMS E-Learning ITG ke API Rekomendasi wajib menyertakan token autentikasi rahasia (*Pre-Shared Secret*) melalui Header `Authorization`.

* **Header Name**: `Authorization`
* **Format Value**: `Bearer <ITG_API_TOKEN>`

> **Catatan:** Request tanpa token atau dengan token yang tidak cocok akan ditolak otomatis oleh *middleware* server dengan kode HTTP `401 Unauthorized`.

---

## 3. Spesifikasi Endpoint

### Endpoint 1: Processing & Calculation (Trigger)

Digunakan untuk memicu kalkulasi rekomendasi video saat ada perubahan atau penambahan materi baru di LMS.

* **Method**: `POST`
* **URL Path**: `/api/process`
* **Content-Type**: `application/json`

#### Request Headers

| Header | Value | Required |
| :--- | :--- | :--- |
| `Authorization` | `Bearer <ITG_API_TOKEN>` | Ya |
| `Accept` | `application/json` | Ya |
| `Content-Type`| `application/json` | Ya |

#### Request Body (JSON)

```json
{
  "kode_prodi": "IF",
  "kode_mata_kuliah": "IFPPL8391",
  "pertemuan": 4,
  "kelas": "B",
  "judul_materi": "Pemrograman Berorientasi Objek dan Konsep Encapsulation",
  "deskripsi_materi": "Membahas tentang pilar PBO khususnya enkapsulasi, access modifier public, private, protected, serta penerapan setter dan getter dalam PHP."
}
```

#### Parameter Field Definition

| Field | Type | Description |
| :--- | :--- | :--- |
| `kode_prodi` | String | Kode program studi |
| `kode_mata_kuliah` | String | Kode unik mata kuliah |
| `pertemuan` | Integer | Nomor pertemuan ke- (misal: 1, 2, 4) |
| `kelas` | String | Identifier kelas (misal: A, B, R) |
| `judul_materi` | String | Judul modul/materi perkuliahan |
| `deskripsi_materi` | String | Deskripsi konten materi perkuliahan |

#### Response Success (`200 OK`)

```json
{
  "status": "success",
  "message": "Kalkulasi rekomendasi video berhasil diproses dan disimpan.",
  "data": {
    "kode_mata_kuliah": "IFPPL8391",
    "pertemuan": 4,
    "kelas": "B",
    "recommended_videos_count": 5,
    "videos": [
      {
        "youtube_id": "abc123XYZ",
        "similarity_score": 0.4215
      },
      {
        "youtube_id": "def456UVW",
        "similarity_score": 0.3102
      },
      {
        "youtube_id": "ghi789RST",
        "similarity_score": 0.2854
      },
      {
        "youtube_id": "jkl012OPQ",
        "similarity_score": 0.1982
      },
      {
        "youtube_id": "mno345LMN",
        "similarity_score": 0.0812
      }
    ]
  }
}
```

---

### Endpoint 2: Fetch Recommendation (Fetch Video)

Bisa digunakan oleh LMS untuk mengambil ID video rekomendasi yang sudah tersimpan di database untuk ditampilkan pada antarmuka pengguna.

* **Method**: `GET`
* **URL Path**: `/api/rekomendasi`

#### Request Headers

| Header | Value | Required |
| :--- | :--- | :--- |
| `Authorization` | `Bearer <ITG_API_TOKEN>` | Ya |
| `Accept` | `application/json` | Ya |

#### Query Parameters

| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `kode_mata_kuliah` | String | Ya | Kode mata kuliah yang dicari |
| `pertemuan` | Integer| Ya | Pertemuan ke- |
| `kelas` | String | Ya | Kelas perkuliahan |

**Contoh URL Request:**
```http
GET /api/rekomendasi?kode_mata_kuliah=IFPPL8391&pertemuan=4&kelas=B
```

#### Response Success (`200 OK`)

```json
{
  "status": "success",
  "data": {
    "kode_mata_kuliah": "IFPPL8391",
    "pertemuan": 4,
    "kelas": "B",
    "skor_kemiripan": [0.4215, 0.3102, 0.2854, 0.1982, 0.0812],
    "video_ids": [
      "abc123XYZ",
      "def456UVW",
      "ghi789RST",
      "jkl012OPQ",
      "mno345LMN"
    ]
  }
}
```

#### Response Not Found (`404 Not Found`)
Terjadi jika materi belum pernah diisi atau belum melalui proses pemicuan (`POST /api/process`).

```json
{
  "status": "error",
  "message": "Rekomendasi video belum dikalkulasi untuk materi ini."
}
```

---

## 4. Diagram Alur Integrasi (Sequence Flow)

```text
[ Dosen ]               [ LMS E-Learning ITG ]               [ API Recommendation ]
    │                             │                                    │
    │ 1. Upload/Update Materi     │                                    │
    ├────────────────────────────>│                                    │
    │                             │ 2. POST /api/process (JSON)        │
    │                             ├───────────────────────────────────>│
    │                             │    (With Bearer Token)             │ 3. Exec: Preprocessing,
    │                             │                                    │    YouTube Fetch, TF-IDF,
    │                             │                                    │    Cosine Similarity
    │                             │ 4. Response 200 OK + Saved in DB   │ 4. Save to DB
    │                             │<───────────────────────────────────┤ (`updateOrCreate`)
    │                             │                                    │
[ Mahasiswa ]                     │                                    │
    │                             │                                    │
    │ 5. Buka Halaman Materi      │                                    │
    ├────────────────────────────>│                                    │
    │                             │ 6. GET /api/rekomendasi            │
    │                             ├───────────────────────────────────>│
    │                             │ 7. Response 200 OK (Fetch Video)   │ Direct Read from DB
    │                             │<───────────────────────────────────┤ (Fast Response < 100ms)
    │ 8. Tampilkan Video Youtube  │                                    │
    │<────────────────────────────┤                                    │
```
