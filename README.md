# SMARTSIS (Smart Student Intelligence System)

[cite_start]Platform Monitoring dan Pembinaan Siswa Terintegrasi untuk SMAN 5 Bekasi[cite: 4, 5, 9]. [cite_start]Sistem ini dirancang untuk mengintegrasikan aktivitas kesiswaan (kehadiran, pelanggaran, kredit poin, prestasi, perizinan) ke dalam satu platform digital yang terpusat[cite: 41].

## 🛠️ Stack Teknologi
* [cite_start]**Backend:** Laravel 13 (atau versi stabil terbaru) [cite: 1074, 1076]
* [cite_start]**Frontend:** Blade Template, Livewire, Livewire [cite: 1089, 1090, 1091, 1093, 1095]
* [cite_start]**Database:** MySQL [cite: 1099]
* [cite_start]**Fitur Khusus:** Face-api.js (untuk Face Recognition & Blink Detection pada browser) [cite: 1123, 1125, 1135]

---

## 👥 Role & Hak Akses Pengguna (RBAC)

[cite_start]Sistem ini menggunakan *Role Based Access Control* (RBAC) yang terbagi menjadi **9 role utama**[cite: 435, 442, 444]:

1.  **Super Admin**
    * [cite_start]**Fungsi:** Pengelola Sistem secara keseluruhan[cite: 444].
    * [cite_start]**Akses:** Mengelola master data (siswa, guru, pengguna), konfigurasi sistem (aturan poin, SP), dan memiliki akses ke seluruh dashboard serta laporan[cite: 448, 450, 457, 464].
2.  **Kepala Sekolah**
    * [cite_start]**Fungsi:** Monitoring dan Evaluasi[cite: 444].
    * [cite_start]**Akses:** Memantau dashboard sekolah, statistik, dan laporan menyeluruh[cite: 471, 475]. [cite_start]Tidak dapat mengubah data operasional[cite: 484].
3.  **Wakasek Kesiswaan**
    * [cite_start]**Fungsi:** Monitoring Kesiswaan[cite: 444].
    * [cite_start]**Akses:** Memantau kehadiran, pelanggaran, poin, prestasi, izin, dan rekap laporan kesiswaan[cite: 489, 495].
4.  **Guru BK**
    * [cite_start]**Fungsi:** Pembinaan dan Konseling[cite: 444].
    * [cite_start]**Akses:** Menginput dan memverifikasi pelanggaran, memberikan catatan pembinaan/konseling, memberikan persetujuan Surat Peringatan (SP), dan memantau siswa berisiko[cite: 502, 506, 509, 513].
5.  **Wali Kelas**
    * [cite_start]**Fungsi:** Monitoring Siswa Per Kelas[cite: 444].
    * [cite_start]**Akses:** Memantau data, kehadiran, pelanggaran, dan poin khusus untuk siswa pada kelas yang diampu saja[cite: 520, 531].
6.  **Guru Piket**
    * [cite_start]**Fungsi:** Monitoring Operasional Harian[cite: 444].
    * [cite_start]**Akses:** Memantau keterlambatan/kehadiran, menginput pelanggaran tertentu, dan menyetujui izin harian sesuai kebijakan[cite: 536, 539, 542].
7.  **Guru Mata Pelajaran**
    * [cite_start]**Fungsi:** Monitoring Informasi Siswa[cite: 444].
    * [cite_start]**Akses:** Melihat profil, status kehadiran, dan kedisiplinan siswa (hanya akses baca)[cite: 548, 549, 550, 552].
8.  **Siswa**
    * [cite_start]**Fungsi:** Pengguna Utama Data Pribadi[cite: 444].
    * [cite_start]**Akses:** Melihat profil pribadi, riwayat kehadiran, poin, prestasi, dan mengajukan izin[cite: 557, 560, 565]. [cite_start]Tidak dapat mengakses data siswa lain[cite: 569].
9.  **Orang Tua/Wali**
    * [cite_start]**Fungsi:** Monitoring Aktivitas Siswa[cite: 444].
    * [cite_start]**Akses:** Memantau kehadiran, poin, pelanggaran, prestasi, dan surat peringatan[cite: 574]. [cite_start]Hanya dapat mengakses data anak yang terhubung dengan akun tersebut[cite: 584].

---

## 🚫 Mengapa Tidak Ada Halaman Register?

Aplikasi SMARTSIS **tidak menyediakan** halaman registrasi publik mandiri (Sign Up) bagi pengguna karena beberapa alasan arsitektur dan keamanan:

* [cite_start]**Autentikasi Terpusat:** Dokumen spesifikasi keamanan secara tegas mewajibkan bahwa "Pengguna wajib login menggunakan akun yang diberikan"[cite: 1194]. Hal ini berarti inisiasi pembuatan akun sepenuhnya berada di tangan pihak sekolah.
* [cite_start]**Manajemen Oleh Super Admin:** Seluruh siklus hidup akun pengguna dikelola melalui "Manajemen Pengguna", di mana hanya Admin yang memiliki hak fungsional untuk menambah, mengubah, menonaktifkan, atau mereset password akun[cite: 618, 620, 622].
* [cite_start]**Integritas Relasi Data yang Ketat:** Sistem menerapkan pembatasan hak akses yang sangat sensitif (misalnya, Orang Tua hanya dapat melihat data anaknya sendiri [cite: 584][cite_start], Wali Kelas hanya melihat kelasnya [cite: 531][cite_start], Siswa tidak melihat data temannya [cite: 569]). [cite_start]Membuka halaman register publik akan membuka celah keamanan kerahasiaan informasi siswa [cite: 441] [cite_start]dan meningkatkan risiko ketidaksesuaian pemetaan data (RBAC)[cite: 435]. [cite_start]Pembuatan akun terintegrasi langsung saat Admin melakukan input/import master data[cite: 627, 632].