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

---

## 🔑 Akun Uji Coba & Checklist Pengujian per Role

> ⚠️ **Akun di bawah ini hanya untuk server pengujian (staging).** Semuanya dibuat oleh
> `DemoAccountSeeder` dengan password yang sama dan sudah diketahui umum. Jangan pernah
> menjalankan `php artisan migrate --seed` di server produksi, dan nonaktifkan seluruh akun
> `@smartsis.test` sebelum sistem dipakai sungguhan:
>
> ```bash
> php artisan tinker --execute 'App\Models\User::where("email","like","%@smartsis.test")->update(["is_active" => false]);'
> ```

### Daftar Akun

**Super Admin** : email : `super_admin@smartsis.test`, password : `password`
**Kepala Sekolah** : email : `kepala_sekolah@smartsis.test`, password : `password`
**Wakasek Kesiswaan** : email : `wakasek_kesiswaan@smartsis.test`, password : `password`
**Guru BK** : email : `guru_bk@smartsis.test`, password : `password`
**Wali Kelas** : email : `wali_kelas@smartsis.test`, password : `password`
**Guru Piket** : email : `guru_piket@smartsis.test`, password : `password`
**Guru Mata Pelajaran** : email : `guru_mapel@smartsis.test`, password : `password`
**Siswa** : email : `siswa@smartsis.test`, password : `password`
**Orang Tua/Wali** : email : `orang_tua@smartsis.test`, password : `password`

Data pendukung yang ikut dibuat seeder: jurusan **IPA** & **IPS**, tahun ajaran **2025/2026**,
kelas **XI IPA 1** (wali kelasnya akun Wali Kelas di atas), siswa **"Siswa Demo"** NIS `2025001`,
dan akun Orang Tua yang sudah terhubung ke siswa tersebut.

### Matriks Hak Akses

Diambil dari `UserRole::defaultPermissions()` di `app/Enums/UserRole.php`.
Tanda ✅ = boleh, kosong = tidak boleh.

| Hak Akses | Super Admin | Kepsek | Wakasek | Guru BK | Wali Kelas | Guru Piket | Guru Mapel | Siswa | Ortu |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Lihat Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Kelola Master Data | ✅ | | | | | | | | |
| Kelola Peran & Hak Akses | ✅ | | | | | | | | |
| Lihat Absensi | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Kelola Absensi | ✅ | | | | | ✅ | | | |
| Lihat Pelanggaran | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Catat Pelanggaran | ✅ | | | | | ✅ | | | |
| Kelola Pelanggaran | ✅ | | | ✅ | | | | | |
| Lihat Poin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | | ✅ | ✅ |
| Kelola Poin | ✅ | | | ✅ | | | | | |
| Lihat Prestasi | ✅ | ✅ | ✅ | ✅ | ✅ | | | ✅ | ✅ |
| Ajukan Prestasi | ✅ | | | | | | | ✅ | |
| Ubah Prestasi | ✅ | | | ✅ | | | | ✅ | |
| Kelola Prestasi | ✅ | | | ✅ | | | | | |
| Lihat Perizinan | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | | ✅ | ✅ |
| Ajukan Perizinan | ✅ | | | | | | | ✅ | |
| Kelola Perizinan | ✅ | | | | | ✅ | | | |
| Lihat Surat Peringatan | ✅ | ✅ | ✅ | ✅ | ✅ | | | ✅ | ✅ |
| Kelola Surat Peringatan | ✅ | | | ✅ | | | | | |

Untuk memastikan hak akses di database benar-benar sesuai tabel di atas, jalankan:

```bash
php artisan tinker --execute 'Spatie\Permission\Models\Role::with("permissions")->get()->each(fn($r) => print($r->name.": ".$r->permissions->pluck("name")->implode(", ")."\n\n"));'
```

### Checklist Pengujian per Role

**Super Admin** — `super_admin@smartsis.test`
Pemilik seluruh akses tanpa terkecuali (lewat `Gate::before`, bukan daftar permission).
- [ ] Master Data: tambah/ubah tahun ajaran, jurusan, kelas, guru, siswa
- [ ] Manajemen Pengguna: buat akun, reset password, nonaktifkan akun
- [ ] Manajemen Peran: ubah hak akses role lain, tombol "Kembalikan ke Bawaan"
- [ ] Pengaturan Absensi (jam check-in, ambang terlambat), Pengaturan Poin, Pengaturan SP
- [ ] Semua menu tampil di sidebar tanpa ada yang tersembunyi

**Kepala Sekolah** — `kepala_sekolah@smartsis.test`
Murni pemantauan, tidak boleh mengubah data operasional.
- [ ] Dashboard statistik sekolah tampil
- [ ] Bisa buka daftar absensi, pelanggaran, poin, prestasi, izin, dan SP
- [ ] **Tidak ada** tombol tambah/ubah/hapus/verifikasi di halaman-halaman tersebut
- [ ] Menu Master Data & Manajemen Peran tidak muncul

**Wakasek Kesiswaan** — `wakasek_kesiswaan@smartsis.test`
Hak aksesnya identik dengan Kepala Sekolah.
- [ ] Rekap kesiswaan (kehadiran, pelanggaran, poin, prestasi, izin) bisa dibuka
- [ ] Sama seperti Kepsek: read-only, tidak ada aksi ubah

**Guru BK** — `guru_bk@smartsis.test`
Role paling berat di sisi pembinaan.
- [ ] Catat pelanggaran baru, lalu ubah dan verifikasi pelanggaran tersebut
- [ ] Cek poin siswa otomatis berkurang setelah pelanggaran diverifikasi
- [ ] Kelola Aturan Poin (tambah/ubah rule) dan penyesuaian poin manual
- [ ] Verifikasi (setujui/tolak) prestasi yang diajukan siswa, dan ubah isinya
- [ ] Terbitkan Surat Peringatan + cetak suratnya, atur ambang batas SP
- [ ] **Tidak bisa** kelola absensi maupun menyetujui izin

**Wali Kelas** — `wali_kelas@smartsis.test`
Kuncinya bukan cuma hak akses, tapi **batas kelas binaan**.
- [ ] Hanya siswa kelas **XI IPA 1** yang tampil di daftar siswa/absensi/poin
- [ ] Siswa dari kelas lain **tidak** muncul dan tidak bisa dibuka lewat URL langsung
- [ ] Read-only: tidak ada tombol input pelanggaran atau verifikasi

**Guru Piket** — `guru_piket@smartsis.test`
Role operasional harian.
- [ ] Buka halaman scan kiosk absensi, tes scan wajah siswa
- [ ] Koreksi status kehadiran siswa secara manual (hadir/terlambat/alpha)
- [ ] Ubah Pengaturan Absensi (jam check-in, ambang terlambat)
- [ ] Catat pelanggaran baru — tapi **tidak bisa** memverifikasinya
- [ ] Setujui/tolak izin siswa dan cetak surat izinnya
- [ ] Menu Prestasi & Surat Peringatan tidak muncul

**Guru Mata Pelajaran** — `guru_mapel@smartsis.test`
Hak akses paling sempit di antara guru — hanya baca.
- [ ] Bisa lihat dashboard, daftar absensi, dan daftar pelanggaran
- [ ] Menu Poin, Prestasi, Perizinan, dan Surat Peringatan **tidak muncul**
- [ ] Tidak ada tombol aksi apa pun

**Siswa** — `siswa@smartsis.test`
Pengguna terbanyak, alurnya paling panjang. Uji berurutan:
- [ ] Login pertama → diarahkan ke **wizard pendaftaran wajah** (3× capture)
- [ ] Selesai daftar wajah → bisa masuk dashboard
- [ ] **Absensi mandiri**: scan wajah + deteksi kedip + selfie tersimpan dan tampil
- [ ] Riwayat absensi menampilkan foto selfie dan lokasi
- [ ] Ajukan izin baru, lalu pantau statusnya sampai disetujui Guru Piket
- [ ] Ajukan prestasi, lalu **ubah** prestasi selama statusnya masih menunggu verifikasi
- [ ] Setelah diverifikasi Guru BK, prestasi **tidak bisa** diubah lagi
- [ ] Lihat poin pribadi dan surat peringatan (jika ada)
- [ ] **Tidak bisa** melihat data siswa lain, termasuk lewat URL langsung

**Orang Tua/Wali** — `orang_tua@smartsis.test`
Read-only dan terikat pada anak yang terhubung.
- [ ] Dashboard menampilkan data **"Siswa Demo"** (anak yang terhubung)
- [ ] Bisa pantau kehadiran, poin, pelanggaran, prestasi, dan surat peringatan anaknya
- [ ] Siswa lain **tidak** muncul dan tidak bisa diakses lewat URL langsung
- [ ] Tidak ada tombol aksi apa pun

### Catatan Penting Saat Pengujian

1. **Jam absensi.** Check-in ditolak sebelum **07:00** dan berstatus *Terlambat* setelah
   **07:30**. Jika pengujian dilakukan siang hari, longgarkan dulu lewat Pengaturan Absensi
   (Super Admin / Guru Piket), lalu kembalikan ke nilai semula setelah selesai.
2. **Kamera wajib HTTPS.** Fitur pendaftaran wajah dan absensi tidak akan jalan jika situs
   dibuka lewat HTTP atau via alamat IP.
3. **Hak akses bisa berbeda dari tabel di atas** jika Super Admin sudah mengubahnya di
   "Manajemen Peran" — seeder hanya menerapkan bawaan pada role yang baru dibuat. Gunakan
   perintah `tinker` di bagian Matriks Hak Akses untuk melihat kondisi sebenarnya.
