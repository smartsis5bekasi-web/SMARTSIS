# Kerangka Acuan Kerja (KAK) — SMARTSIS

> **Smart Student Intelligence System**
> Platform Monitoring dan Pembinaan Siswa Terintegrasi

---

## Informasi Dokumen

| Field | Nilai |
|---|---|
| Nama Dokumen | Kerangka Acuan Kerja (KAK) |
| Nama Sistem | SMARTSIS (Smart Student Intelligence System) |
| Jenis Pekerjaan | Pengembangan Platform Monitoring dan Pembinaan Siswa Terintegrasi |
| Klien | SMAN 5 Bekasi |
| Pengembang | Sibermuda Indonesia (Digital Solution Provider) |
| Project Lead | Rizky Muhamad Ramdan |
| Versi Dokumen | 1.0 |
| Tanggal | Juni 2026 |
| Status | Draft Final |
| Kerahasiaan | Internal Project Document |

**Halaman Pengesahan:** Dokumen ini menjadi acuan pelaksanaan pengembangan SMARTSIS antara SMAN 5 Bekasi (Pihak Sekolah, ditandatangani Kepala SMAN 5 Bekasi) dan Sibermuda Indonesia (Pihak Pengembang, ditandatangani Rizky Muhamad Ramdan selaku Lead Project). Bekasi, Juni 2026.

---

## BAB I — PENDAHULUAN

### 1.1 Nama Pekerjaan
Pengembangan Platform Monitoring dan Pembinaan Siswa Terintegrasi — **SMARTSIS (Smart Student Intelligence System)**. Digunakan sebagai sistem monitoring, pembinaan, dan pengelolaan aktivitas siswa di SMAN 5 Bekasi.

Sistem mengintegrasikan aktivitas kesiswaan ke dalam satu platform digital terpusat: kehadiran, pelanggaran, kredit poin, prestasi, perizinan, pembinaan, serta monitoring orang tua.

### 1.2 Dasar Pelaksanaan
Didasari kebutuhan SMAN 5 Bekasi meningkatkan efektivitas pengelolaan data kesiswaan melalui TI terintegrasi, kebutuhan sistem monitoring yang cepat/akurat/transparan, serta mekanisme yang menghubungkan seluruh pihak (siswa, guru, wali kelas, guru BK, wakasek kesiswaan, kepala sekolah, hingga orang tua/wali). SMARTSIS dipandang sebagai solusi pendukung transformasi digital sekolah.

### 1.3 Maksud Pekerjaan
Membangun sistem informasi berbasis web sebagai sarana monitoring dan pembinaan siswa terintegrasi, menjadi pusat informasi aktivitas siswa yang dapat diakses sesuai hak akses, serta menyatukan pengelolaan data kesiswaan yang sebelumnya terpisah.

### 1.4 Tujuan Pekerjaan
1. Mengintegrasikan proses monitoring dan pembinaan siswa ke dalam satu sistem terpusat.
2. Meningkatkan efektivitas pengelolaan data kehadiran siswa.
3. Mempermudah monitoring pelanggaran dan pembinaan siswa.
4. Mengotomatisasi pengelolaan kredit poin kedisiplinan.
5. Menyediakan mekanisme penerbitan surat peringatan yang terstruktur.
6. Mempermudah pengajuan dan pengelolaan perizinan siswa.
7. Menyediakan sarana monitoring aktivitas siswa bagi orang tua.
8. Menyediakan dashboard monitoring bagi pihak sekolah.
9. Mendukung pengambilan keputusan berbasis data.
10. Mendukung program transformasi digital sekolah.

### 1.5 Sasaran Pekerjaan
**Operasional:** sistem monitoring terintegrasi; absensi berbasis Face Recognition; pengelolaan poin kedisiplinan; pencatatan pelanggaran; perizinan digital; dashboard monitoring sekolah.

**Administratif:** mengurangi dokumen manual; meningkatkan akurasi data; mempermudah penyusunan laporan; meningkatkan efisiensi pengelolaan data kesiswaan.

**Strategis:** mendukung digitalisasi sekolah; meningkatkan keterlibatan orang tua; mendukung keputusan berbasis data; meningkatkan kualitas layanan pendidikan.

### 1.6 Hasil yang Diharapkan
Tersedianya sistem SMARTSIS sebagai platform monitoring dan pembinaan siswa terintegrasi yang mampu meningkatkan efektivitas monitoring, mempermudah pembinaan, meningkatkan transparansi, mempercepat administrasi, mendukung transformasi digital, dan menyediakan data akurat/terstruktur. Juga menjadi fondasi pengembangan ekosistem sekolah digital pada tahap berikutnya.

### 1.7 Lokasi Pekerjaan
SMAN 5 Bekasi — pengguna utama dan lokasi implementasi.

### 1.8 Jangka Waktu Pelaksanaan
**2 (Dua) Bulan**, dihitung sejak penandatanganan dokumen kerja sama dan diterimanya data pendukung dari sekolah.

Tahapan: Analisis kebutuhan → Perancangan sistem → Pengembangan sistem → Pengujian sistem → Implementasi → Pelatihan pengguna → Serah terima sistem.

---

## BAB II — LATAR BELAKANG PEKERJAAN

### 2.1 Kondisi Eksisting
Monitoring & pembinaan siswa mencakup kehadiran, keterlambatan, pelanggaran tata tertib, prestasi, pembinaan, dan komunikasi sekolah–orang tua. Sebagian besar proses masih terpisah dan belum terintegrasi; data tersimpan pada media berbeda sehingga menyulitkan monitoring, pelaporan, dan evaluasi. Berpotensi menyebabkan keterlambatan informasi, beban administrasi tinggi, dan pembinaan kurang optimal.

### 2.2 Permasalahan yang Dihadapi
- **A. Monitoring kehadiran belum optimal** — perlu data kehadiran cepat/akurat, minim penyalahgunaan absensi, dukungan monitoring keterlambatan.
- **B. Data kedisiplinan belum terintegrasi** — pelanggaran/pembinaan/perkembangan belum dalam satu sistem; penelusuran histori lambat.
- **C. Pengelolaan kredit poin masih manual** — perhitungan poin & pencatatan kompleks dan rentan ketidaksesuaian data.
- **D. Monitoring orang tua belum real-time** — akses informasi aktivitas siswa terbatas.
- **E. Administrasi pembinaan lama** — penerbitan SP, laporan, dan dokumentasi membutuhkan kerja administratif besar.
- **F. Belum ada sistem analisis aktivitas siswa** — perlu sarana identifikasi siswa yang butuh perhatian khusus.

### 2.3 Kebutuhan Pengembangan Sistem
1. **Integrasi Data** — menyatukan seluruh data aktivitas siswa.
2. **Monitoring Real-Time** — informasi cepat dan diperbarui langsung.
3. **Otomatisasi Proses** — mengurangi pekerjaan administratif manual.
4. **Transparansi Informasi** — akses sesuai hak akses.
5. **Analisis Aktivitas Siswa** — dasar pembinaan dan pengambilan keputusan.

### 2.4 Solusi yang Diusulkan
Pembangunan SMARTSIS yang mengelola: data kehadiran, keterlambatan, pelanggaran, kredit poin, prestasi, perizinan, surat peringatan, dashboard monitoring, dan informasi orang tua — tersimpan dalam satu basis data terpusat sesuai kewenangan pengguna.

### 2.5 Manfaat Implementasi
- **Sekolah:** efektivitas pengelolaan data, kurangi beban administrasi, mempermudah laporan.
- **Kepala Sekolah:** informasi cepat/akurat, keputusan berbasis data.
- **Guru BK & Wali Kelas:** mempermudah monitoring/pembinaan, histori aktivitas lengkap.
- **Orang Tua:** akses transparan, dukung pengawasan di keluarga.
- **Siswa:** kesadaran kedisiplinan, dorong perilaku positif via poin & prestasi.

### 2.6 Harapan Implementasi
Tercipta sistem monitoring & pembinaan yang modern, efektif, terintegrasi; menjadi fondasi transformasi digital bidang kesiswaan SMAN 5 Bekasi.

---

## BAB III — TUJUAN DAN SASARAN PEKERJAAN

### 3.1 Tujuan Umum
Membangun platform monitoring & pembinaan siswa terintegrasi yang efektif, efisien, transparan, dan berbasis TI; menjadi pusat informasi aktivitas siswa sesuai hak akses; meningkatkan kualitas monitoring, mempercepat administrasi, dan mendukung keputusan berbasis data.

### 3.2 Tujuan Khusus
- **A.** Digitalisasi monitoring kehadiran (absensi Face Recognition).
- **B.** Digitalisasi pengelolaan kedisiplinan (poin, pelanggaran, pembinaan terintegrasi).
- **C.** Meningkatkan transparansi informasi bagi sekolah & orang tua.
- **D.** Mengurangi administrasi manual (pencatatan pelanggaran, poin, SP, laporan).
- **E.** Mendukung pengambilan keputusan via dashboard & laporan.
- **F.** Mendukung transformasi digital sekolah.

### 3.3 Sasaran Pengguna Sistem
1. Kepala Sekolah — informasi strategis kedisiplinan & aktivitas siswa.
2. Wakasek Kesiswaan — pengelolaan & pembinaan kesiswaan.
3. Guru BK — pembinaan, konseling, pengelolaan pelanggaran.
4. Wali Kelas — perkembangan siswa di kelasnya.
5. Guru Piket — monitoring operasional harian.
6. Guru Mata Pelajaran — akses informasi tertentu terkait siswa.
7. Siswa — info pribadi, poin, pelanggaran, prestasi, ajukan perizinan.
8. Orang Tua/Wali — info perkembangan siswa secara transparan.
9. Administrator Sistem — pengelolaan data master & konfigurasi.

### 3.4 Sasaran Fungsional Sistem
- **Monitoring:** kehadiran, keterlambatan, pelanggaran, prestasi, poin kedisiplinan.
- **Administrasi:** data siswa, guru, kelas, jurusan, pengguna.
- **Pembinaan:** pelanggaran, poin, surat peringatan, konseling & tindak lanjut.
- **Pelaporan:** rekap kehadiran, pelanggaran, poin, perizinan, statistik monitoring.
- **Analisis:** tingkat kedisiplinan, risiko siswa, Smart Recommendation Engine.

### 3.5 Sasaran Implementasi
A. Meningkatkan efektivitas monitoring · B. Efisiensi administrasi · C. Kualitas pembinaan · D. Keterlibatan orang tua · E. Mendukung transformasi digital.

### 3.6 Indikator Keberhasilan

| No | Indikator |
|---|---|
| 1 | Sistem dapat digunakan seluruh pengguna sesuai hak akses |
| 2 | Absensi siswa dapat dilakukan dengan Face Recognition |
| 3 | Data kehadiran tersimpan otomatis |
| 4 | Sistem kredit poin berjalan sesuai konfigurasi |
| 5 | Pelanggaran siswa tercatat dengan baik |
| 6 | Surat peringatan dapat dihasilkan sistem |
| 7 | Orang tua dapat memantau aktivitas siswa |
| 8 | Dashboard monitoring dapat digunakan pihak sekolah |
| 9 | Laporan dapat dihasilkan sesuai kebutuhan |
| 10 | Sistem dapat digunakan dalam operasional sekolah |

---

## BAB IV — RUANG LINGKUP PEKERJAAN

### 4.1 Ruang Lingkup Umum
Analisis kebutuhan, perancangan, pengembangan aplikasi, pengujian, implementasi, pelatihan pengguna, dan serah terima sistem kepada SMAN 5 Bekasi. Menghasilkan platform monitoring & pembinaan siswa terintegrasi berbasis web.

### 4.2 Ruang Lingkup Pengembangan Sistem (Modul)

**A. Modul Master Data**
Fitur: data siswa, guru, kelas, jurusan, tahun ajaran, pengguna, role & hak akses.
Output: data master terpusat; struktur organisasi pengguna sistem.

**B. Modul Smart Attendance** (absensi Face Recognition)
Fitur: registrasi wajah siswa, verifikasi wajah, Blink Detection, absensi masuk, absensi pulang, monitoring kehadiran, rekap absensi.
Output: data kehadiran; data keterlambatan.

**C. Modul Monitoring Kehadiran**
Fitur: monitoring hadir, terlambat, izin, sakit, alpha, statistik kehadiran.
Output: laporan & statistik kehadiran.

**D. Modul Dynamic Point System**
Fitur: pengaturan poin awal, kategori poin, pengurangan poin otomatis, penambahan poin otomatis, histori perubahan poin.
Output: data perkembangan poin siswa.

**E. Modul Violation Management** (pelanggaran)
Fitur: input pelanggaran, kategori pelanggaran, upload bukti, verifikasi pelanggaran, histori pelanggaran.
Output: data pelanggaran; histori pembinaan.

**F. Modul Achievement Management** (prestasi)
Fitur: input prestasi, upload bukti prestasi, penambahan poin otomatis, histori prestasi.
Output: data prestasi siswa.

**G. Modul Warning Letter Automation** (surat peringatan)
Fitur: SP1, SP2, SP3, persetujuan Guru BK, cetak PDF.
Output: dokumen surat peringatan.

**H. Modul Digital Permission System** (perizinan)
Fitur: pengajuan izin, persetujuan izin, monitoring status izin, histori izin.
Output: data perizinan siswa.

**I. Modul Parent Monitoring Dashboard**
Fitur: monitoring kehadiran, poin, pelanggaran, prestasi, surat peringatan, izin.
Output: informasi siswa untuk orang tua.

**J. Modul School Monitoring Dashboard**
Fitur: statistik kehadiran, pelanggaran, poin, prestasi, grafik monitoring.
Output: dashboard monitoring sekolah.

**K. Modul Smart Recommendation Engine** (berbasis aturan)
Fitur: analisis kehadiran, keterlambatan, pelanggaran, poin siswa, rekomendasi pembinaan.
Output: informasi rekomendasi pembinaan.

### 4.3 Ruang Lingkup Implementasi
- **Persiapan Sistem:** konfigurasi aplikasi, database, pengguna.
- **Pengujian Sistem:** pengujian fungsi, hak akses, absensi wajah, laporan.
- **Pelatihan Pengguna:** Kepala Sekolah, Wakasek Kesiswaan, Guru BK, Wali Kelas, Admin Sistem.
- **Pendampingan Implementasi:** penggunaan awal & dukungan teknis selama masa implementasi.

### 4.4 Ruang Lingkup yang TIDAK Termasuk (v1.0)
Aplikasi Android Native · iOS Native · CBT/Ujian Online · E-Rapor · Pembayaran SPP · Perpustakaan · Inventaris Sekolah · Kepegawaian · Integrasi Dapodik · WhatsApp Gateway · SMS Gateway · Fingerprint Device · Pengadaan Server Fisik · Pengadaan Tablet Absensi · Hosting & Domain Tahunan.

### 4.5 Batasan Pengembangan
Perubahan ruang lingkup setelah pengembangan berjalan dikategorikan pekerjaan tambahan (penyesuaian waktu/biaya berdasarkan kesepakatan).

### 4.6 Deliverable Ruang Lingkup Pekerjaan
1. Aplikasi SMARTSIS versi 1.0
2. Database sistem
3. Source code aplikasi
4. Dokumen KAK
5. Dokumen panduan penggunaan
6. Dokumen serah terima
7. Garansi sesuai kesepakatan

---

## BAB V — ANALISIS PENGGUNA SISTEM

### 5.1 Gambaran Pengguna
Menggunakan **Role Based Access Control (RBAC)**. Tujuan RBAC: meningkatkan keamanan data, mengurangi penyalahgunaan akses, mempermudah pengelolaan pengguna, menjaga kerahasiaan informasi siswa. Terdapat **9 role utama**.

### 5.2 Struktur Pengguna Sistem

| No | Role | Fungsi Utama |
|---|---|---|
| 1 | Super Admin | Pengelola Sistem |
| 2 | Kepala Sekolah | Monitoring dan Evaluasi |
| 3 | Wakasek Kesiswaan | Monitoring Kesiswaan |
| 4 | Guru BK | Pembinaan dan Konseling |
| 5 | Wali Kelas | Monitoring Siswa Per Kelas |
| 6 | Guru Piket | Monitoring Operasional Harian |
| 7 | Guru Mata Pelajaran | Monitoring Informasi Siswa |
| 8 | Siswa | Pengguna Utama Data Pribadi |
| 9 | Orang Tua/Wali | Monitoring Aktivitas Siswa |

### 5.3 Analisis Hak Akses Pengguna

**A. Super Admin** — hak akses tertinggi, mengelola sistem keseluruhan.
- Master Data: kelola siswa, guru, kelas, jurusan, tahun ajaran, pengguna.
- Konfigurasi Sistem: kelola role, permission, kategori pelanggaran, kategori prestasi, aturan poin, aturan SP.
- Monitoring: akses seluruh dashboard & laporan.

**B. Kepala Sekolah** — fokus monitoring & evaluasi.
- Dashboard: dashboard sekolah, statistik kehadiran/pelanggaran/poin.
- Laporan: kehadiran, pelanggaran, poin siswa, surat peringatan.
- Monitoring: seluruh siswa & kelas.
- *Batasan:* tidak dapat mengubah data operasional.

**C. Wakasek Kesiswaan** — monitoring kesiswaan menyeluruh.
- Monitoring: kehadiran, pelanggaran, poin, prestasi, izin siswa, surat peringatan.
- Laporan: rekap kesiswaan, statistik kedisiplinan.

**D. Guru BK** — pembinaan & konseling.
- Pelanggaran: input, verifikasi, histori.
- Pembinaan: catatan pembinaan, catatan konseling.
- Surat Peringatan: persetujuan SP1, SP2, SP3.
- Monitoring: poin siswa, siswa berisiko.

**E. Wali Kelas** — monitoring siswa kelas yang diampu.
- Monitoring Kelas: data siswa, kehadiran, pelanggaran, poin.
- Dashboard: dashboard kelas, statistik kelas.
- Laporan: rekap siswa per kelas.
- *Batasan:* hanya kelas yang diampu.

**F. Guru Piket** — monitoring operasional harian.
- Kehadiran: monitoring keterlambatan & kehadiran.
- Pelanggaran: input pelanggaran tertentu, verifikasi kejadian harian.
- Perizinan: persetujuan izin tertentu sesuai kebijakan sekolah.

**G. Guru Mata Pelajaran** — akses terbatas pendukung pembelajaran.
- Lihat profil siswa, status kehadiran, informasi kedisiplinan.
- *Batasan:* tidak dapat mengubah data pembinaan.

**H. Siswa** — melihat informasi terkait dirinya.
- Profil: profil pribadi, data kelas.
- Monitoring: riwayat kehadiran, poin, pelanggaran, prestasi.
- Perizinan: mengajukan izin, melihat status izin.
- *Batasan:* tidak dapat mengakses data siswa lain.

**I. Orang Tua/Wali** — monitoring anak yang terhubung.
- Monitoring: kehadiran, keterlambatan, poin, pelanggaran, prestasi, surat peringatan.
- Perizinan: melihat histori izin.
- Dashboard: dashboard perkembangan siswa.
- *Batasan:* hanya data anak yang terhubung dengan akun.

### 5.5 Kesimpulan Analisis Pengguna
SMARTSIS membutuhkan pengelolaan hak akses terstruktur. RBAC menjaga keamanan data, meningkatkan efektivitas, dan mendukung monitoring & pembinaan terintegrasi.

---

## BAB VI — KEBUTUHAN FUNGSIONAL SISTEM

> Seluruh fungsi (F-01 s.d. F-40) **wajib tersedia** pada SMARTSIS v1.0.

### 6.2 Modul Autentikasi dan Pengguna

**F-01 Login Sistem** — mekanisme login bagi seluruh pengguna (Super Admin, Kepala Sekolah, Wakasek Kesiswaan, Guru BK, Wali Kelas, Guru Piket, Guru Mata Pelajaran, Siswa, Orang Tua). Output: dashboard sesuai role.

**F-02 Logout Sistem** — keluar dengan aman. Output: session berakhir, kembali ke halaman login.

**F-03 Manajemen Pengguna** — Admin mengelola akun. Fungsi: tambah akun, ubah akun, nonaktifkan akun, reset password.

### 6.3 Modul Master Data

**F-04 Manajemen Data Siswa** — Fungsi: tambah, ubah, hapus, Import Excel, Export Excel. Data: NIS, NISN, Nama, Jenis Kelamin, Kelas, Jurusan, Tahun Masuk, Nama Orang Tua.

**F-05 Manajemen Data Guru** — tambah, edit, hapus guru.

**F-06 Manajemen Kelas** — tambah, edit, hapus kelas.

**F-07 Manajemen Tahun Ajaran** — tambah, aktifkan, tutup tahun ajaran.

### 6.4 Modul Smart Attendance

**F-08 Registrasi Wajah Siswa** — Admin mendaftarkan wajah. Proses: buka kamera → ambil wajah → simpan data wajah → hubungkan ke data siswa. Output: Face Template Siswa.

**F-09 Absensi Masuk** — Siswa absen via Face Recognition. Proses: baca wajah → pencocokan → minta kedipan mata → catat kehadiran. Output: Status Hadir.

**F-10 Absensi Pulang** — metode sama. Output: Status Pulang.

**F-11 Monitoring Kehadiran** — monitoring hadir, terlambat, izin, sakit, alpha.

### 6.5 Modul Dynamic Point System

**F-12 Konfigurasi Poin** — Admin menentukan aturan poin. Contoh:

| Aktivitas | Poin |
|---|---|
| Terlambat | -2 |
| Alpha | -5 |
| Merokok | -30 |
| Juara Lomba | +20 |

**F-13 Perubahan Poin Otomatis** — sistem mengubah poin otomatis. Trigger: pelanggaran, prestasi, keterlambatan, alpha.

**F-14 Histori Poin** — menyimpan seluruh riwayat perubahan poin.

### 6.6 Modul Pelanggaran

**F-15 Input Pelanggaran** — Aktor: Guru BK, Guru Piket. Data: siswa, jenis pelanggaran, kronologi, bukti foto.

**F-16 Verifikasi Pelanggaran** — Aktor: Guru BK. Fungsi: setujui, tolak, revisi.

**F-17 Histori Pelanggaran** — menampilkan seluruh riwayat pelanggaran siswa.

### 6.7 Modul Prestasi

**F-18 Input Prestasi** — Aktor: Guru BK, Wali Kelas, Admin. Data: jenis prestasi, tingkat prestasi, bukti prestasi.

**F-19 Penambahan Poin Prestasi** — sistem menambah poin otomatis berdasarkan prestasi.

### 6.8 Modul Surat Peringatan

**F-20 Konfigurasi SP** — Admin menentukan aturan penerbitan SP. Contoh:

| Poin Tersisa | Surat |
|---|---|
| ≤ 80 | SP1 |
| ≤ 60 | SP2 |
| ≤ 40 | SP3 |

**F-21 Rekomendasi Surat Otomatis** — sistem mendeteksi siswa yang memenuhi syarat SP.

**F-22 Persetujuan Surat** — Aktor: Guru BK. Fungsi: setujui, tolak.

**F-23 Cetak Surat** — Output: PDF SP1, SP2, SP3.

### 6.9 Modul Perizinan

**F-24 Pengajuan Izin** — Aktor: Siswa. Jenis: izin terlambat, izin keluar, izin pulang awal.

**F-25 Persetujuan Izin** — Aktor: Guru Piket, Wali Kelas.

**F-26 Histori Izin** — menampilkan seluruh riwayat izin siswa.

### 6.10 Modul Dashboard Orang Tua

**F-27 Monitoring Kehadiran Anak** — lihat hadir, terlambat, alpha.
**F-28 Monitoring Poin** — lihat poin saat ini & histori poin.
**F-29 Monitoring Pelanggaran** — lihat pelanggaran & bukti pelanggaran.
**F-30 Monitoring Surat Peringatan** — lihat SP yang diterbitkan.

### 6.11 Modul Dashboard Sekolah

**F-31 Dashboard Kepala Sekolah** — statistik siswa, absensi, pelanggaran, poin.
**F-32 Dashboard Wakasek Kesiswaan** — monitoring kedisiplinan, pelanggaran, keterlambatan.
**F-33 Dashboard Guru BK** — kasus aktif, rekomendasi pembinaan, monitoring siswa berisiko.

### 6.12 Smart Recommendation Engine

**F-34 Analisis Aktivitas Siswa** — Parameter: kehadiran, keterlambatan, pelanggaran, poin.

**F-35 Rekomendasi Pembinaan** — Contoh: jika Alpha > 5 kali DAN Poin < 70 → direkomendasikan konseling BK.

**F-36 Deteksi Risiko Kedisiplinan** — Output: Risiko Rendah / Sedang / Tinggi.

### 6.13 Laporan dan Export Data

| Kode | Fungsi | Format |
|---|---|---|
| F-37 | Export Absensi | PDF, Excel |
| F-38 | Export Pelanggaran | PDF, Excel |
| F-39 | Export Poin | PDF, Excel |
| F-40 | Export Perizinan | PDF, Excel |

### 6.14 Kesimpulan Kebutuhan Fungsional
SMARTSIS memiliki **40 kebutuhan fungsional utama** yang wajib tersedia pada v1.0 dan menjadi acuan desain, pengembangan, pengujian, serta serah terima.

---

## BAB VII — ALUR BISNIS SISTEM

### 7.1 Gambaran Umum
Setiap aktivitas siswa menghasilkan data untuk monitoring, pembinaan, evaluasi, dan keputusan. Alur utama: Absensi → Monitoring Kehadiran → Pengelolaan Poin → Pelanggaran → Prestasi → Perizinan → Penerbitan SP → Monitoring Orang Tua → Smart Recommendation Engine (satu basis data terpusat).

### 7.2 Alur Smart Attendance
Tujuan: mencatat kehadiran otomatis via Face Recognition + Blink Detection.
1. Siswa datang → menuju perangkat absensi.
2. Kamera membaca wajah → sistem Face Recognition.
3. Sistem meminta berkedip → Blink Detection validasi.
4. Cocokkan data wajah → catat waktu → tentukan status → simpan ke DB.

Output: Hadir / Terlambat / Tidak Terdaftar.

### 7.3 Alur Monitoring Kehadiran
Absensi masuk → hitung keterlambatan → tentukan status → simpan histori → dashboard diperbarui otomatis. Output: rekap kehadiran, rekap keterlambatan, statistik kehadiran.

### 7.4 Alur Dynamic Point System
Terima aktivitas → cari aturan poin → hitung perubahan → perbarui poin → catat histori.
- Pengurangan poin: terlambat, alpha, pelanggaran.
- Penambahan poin: prestasi, penghargaan.

Output: poin saat ini, histori poin.

### 7.5 Alur Pelanggaran
Guru menemukan pelanggaran → buka modul → pilih siswa → pilih jenis → isi kronologi → unggah bukti → simpan → poin berkurang otomatis → histori siswa diperbarui. Output: data pelanggaran, histori pelanggaran, pengurangan poin.

### 7.6 Alur Prestasi
Guru input prestasi → unggah bukti → verifikasi → tambah poin → histori prestasi diperbarui. Output: data prestasi, penambahan poin.

### 7.7 Alur Surat Peringatan
Monitor poin → bandingkan dengan aturan SP → hasilkan rekomendasi SP → Guru BK terima notifikasi → verifikasi → surat diterbitkan → tersimpan pada histori siswa. Output: SP1 / SP2 / SP3.

### 7.8 Alur Perizinan
Siswa buka menu izin → isi formulir → kirim → Guru Piket terima → verifikasi → setuju/tolak → status diperbarui. Status: Menunggu Persetujuan / Disetujui / Ditolak. Output: histori perizinan.

### 7.9 Alur Monitoring Orang Tua
Orang tua login → sistem tampilkan data siswa → orang tua melihat aktivitas → info terbaru ditampilkan. Informasi: kehadiran, keterlambatan, poin, pelanggaran, prestasi, surat peringatan, perizinan. Output: dashboard orang tua.

### 7.10 Alur Smart Recommendation Engine
Baca data siswa → hitung indikator risiko → evaluasi aturan → hasilkan rekomendasi → Guru BK melihat hasil.

Contoh Rule:
- **Rule 1:** Jika Alpha > 5 kali → Rekomendasi Konseling.
- **Rule 2:** Jika Poin < 70 → Rekomendasi Pembinaan.
- **Rule 3:** Jika Poin < 50 → Rekomendasi Pemanggilan Orang Tua.

Output: Risiko Rendah / Sedang / Tinggi.

### 7.11 Integrasi Antar Modul
Alur integrasi data (berurutan):

```
Smart Attendance
      ↓
Monitoring Kehadiran
      ↓
Dynamic Point System
      ↓
Pelanggaran & Prestasi
      ↓
Smart Recommendation Engine
      ↓
Surat Peringatan
      ↓
Dashboard Sekolah & Orang Tua
```

### 7.12 Kesimpulan Alur Bisnis
Seluruh aktivitas siswa terdokumentasi sistematis, terintegrasi, dan dapat digunakan sebagai dasar monitoring, pembinaan, serta pengambilan keputusan.

---

## BAB VIII — SPESIFIKASI TEKNIS SISTEM

### 8.1 Gambaran Umum Teknologi
Aplikasi berbasis web, teknologi **open source** (tanpa biaya lisensi tambahan), mempertimbangkan kemudahan penggunaan, keamanan data, performa, dan kemudahan pemeliharaan.

### 8.2 Arsitektur Sistem
**Client-Server.**
- **Client:** laptop, komputer, tablet, smartphone.
- **Application Server:** memproses data, menjalankan logika sistem, autentikasi, mengelola hak akses.
- **Database Server:** menyimpan data siswa, guru, absensi, pelanggaran, poin, prestasi, surat peringatan, perizinan.

### 8.3 Teknologi Pengembangan

**Backend Framework — Laravel** (versi **Laravel 12** atau versi stabil terbaru saat implementasi).
Fungsi: business logic, authentication, authorization, API internal, database management.
Alasan: stabil, aman, open source, dokumentasi lengkap, mudah dikembangkan.

**Frontend:**
- **Blade Template** — template engine utama.
- **Livewire** — fitur interaktif tanpa framework frontend kompleks.
- **AlpineJS** — interaksi ringan sisi pengguna.
- **Bootstrap** — tampilan responsif berbagai ukuran perangkat.

### 8.4 Database
**MySQL** — penyimpanan seluruh data. Keunggulan: cepat, stabil, open source, mudah di-backup.
- Master Data: siswa, guru, kelas, jurusan, tahun ajaran, pengguna.
- Data Operasional: kehadiran, pelanggaran, prestasi, poin, perizinan, surat peringatan.

### 8.5 Teknologi Smart Attendance
**Face Recognition** berbasis browser, library **Face-api.js**.
Fungsi: registrasi wajah, verifikasi identitas, pencocokan wajah.
Mekanisme: wajah direkam saat registrasi → hasilkan template → simpan ke DB → saat absensi cocokkan wajah dengan template.

**Blink Detection** — mencegah penyalahgunaan absensi memakai foto.
Mekanisme: wajah terdeteksi → minta berkedip → deteksi gerakan mata → absensi valid.

### 8.6 Dynamic Point Engine
Mengelola kredit poin otomatis. Fungsi: pengurangan poin, penambahan poin, histori poin. Parameter: pelanggaran, keterlambatan, alpha, prestasi. Seluruh aturan poin dapat diubah via panel administrasi **tanpa perubahan kode program**.

### 8.7 Smart Recommendation Engine
Analisis berbasis aturan (**Rule Based System**). **Tidak menggunakan AI/machine learning maupun large language model.** Analisis berdasarkan logika bisnis yang ditentukan sekolah.

Contoh Rule:
- Jika Alpha ≥ 5 kali → Rekomendasi Konseling.
- Jika Poin ≤ 70 → Rekomendasi Pembinaan.
- Jika Poin ≤ 50 → Rekomendasi Pemanggilan Orang Tua.

Keunggulan: ringan, cepat, tidak butuh server AI, mudah dikonfigurasi.

### 8.8 Sistem Hak Akses
**RBAC** dengan 9 role: Super Admin, Kepala Sekolah, Wakasek Kesiswaan, Guru BK, Wali Kelas, Guru Piket, Guru Mata Pelajaran, Siswa, Orang Tua. Setiap role hanya mengakses fitur sesuai kewenangannya.

### 8.9 Keamanan Sistem
- **Authentication:** wajib login dengan akun yang diberikan.
- **Authorization:** hak akses dibatasi berdasarkan role.
- **Password Encryption:** password disimpan dengan metode hashing.
- **Activity Log:** aktivitas penting dicatat untuk audit & monitoring.

### 8.10 Kebutuhan Server

| Komponen | Spesifikasi |
|---|---|
| CPU | 4 Core |
| RAM | 8 GB |
| Storage | 100 GB SSD |
| OS | Ubuntu Server |
| Database | MySQL |
| PHP | 8.3+ |

Jaringan: koneksi internet stabil, jaringan lokal sekolah, akses browser modern.

### 8.11 Kebutuhan Perangkat Absensi
Disediakan pihak sekolah. Rekomendasi: (1) Tablet Android, (2) Smartphone Android, (3) Laptop dengan webcam.
Spesifikasi minimum: kamera HD, RAM 4 GB, browser Chrome terbaru.

### 8.12 Backup dan Pemeliharaan
- Backup database: harian & mingguan.
- Restore database: pemulihan data ketika diperlukan.
- Maintenance: perbaikan bug, penyesuaian minor, monitoring performa.

### 8.13 Kesimpulan Spesifikasi Teknis
Teknologi open source yang stabil, aman, dan mudah dikembangkan; dipilih mempertimbangkan kebutuhan operasional, efisiensi biaya, dan kemudahan implementasi.

---

## BAB IX — DELIVERABLE

> Catatan: Pada PDF sumber, halaman BAB IX (Deliverable) dan BAB X (Kriteria Penerimaan Sistem) tampil kosong/tidak ter-render. Rincian deliverable telah dicantumkan pada **Bagian 4.6** di atas (Aplikasi v1.0, database, source code, dokumen KAK, panduan penggunaan, dokumen serah terima, garansi). Kriteria penerimaan sistem dapat mengacu pada **Indikator Keberhasilan (Bagian 3.6)**.

---

## BAB X — PENUTUP

### 10.1 Kesimpulan
SMARTSIS adalah platform monitoring & pembinaan siswa terintegrasi untuk digitalisasi pengelolaan kesiswaan SMAN 5 Bekasi: kehadiran, kredit poin, pelanggaran, prestasi, perizinan, surat peringatan, hingga monitoring orang tua & sekolah. Dokumen KAK ini menjadi pedoman pelaksanaan: analisis, perancangan, pengembangan, pengujian, implementasi, dan serah terima.

### 10.2 Harapan Implementasi
SMAN 5 Bekasi memiliki sistem modern, terintegrasi, mendukung kualitas layanan pendidikan, budaya disiplin, keterlibatan orang tua, dan keputusan berbasis data; menjadi fondasi ekosistem sekolah digital berkelanjutan.

### 10.3 Ketentuan Perubahan Ruang Lingkup
Perubahan ruang lingkup dilakukan berdasarkan kesepakatan kedua pihak. Perubahan yang berdampak pada fitur/waktu/biaya dituangkan dalam dokumen **Change Request** atau **addendum** perjanjian.

### 10.4 Penutup
KAK ini disusun sebagai acuan pelaksanaan pekerjaan pengembangan SMARTSIS di SMAN 5 Bekasi.

Bekasi, Juni 2026 — Pihak Sekolah (Kepala SMAN 5 Bekasi) & Pihak Pengembang (Rizky Muhamad Ramdan, Sibermuda Indonesia).

---

## Ringkasan Cepat untuk Konteks Kode (Quick Reference)

- **Nama sistem:** SMARTSIS — aplikasi web monitoring & pembinaan siswa.
- **Stack:** Laravel 12 (backend), Blade + Livewire + AlpineJS + Bootstrap (frontend), MySQL (DB), Face-api.js (Face Recognition + Blink Detection di browser), PHP 8.3+, Ubuntu Server.
- **Pola akses:** RBAC, 9 role (Super Admin, Kepala Sekolah, Wakasek Kesiswaan, Guru BK, Wali Kelas, Guru Piket, Guru Mata Pelajaran, Siswa, Orang Tua).
- **Modul inti (A–K):** Master Data, Smart Attendance, Monitoring Kehadiran, Dynamic Point System, Violation Management, Achievement Management, Warning Letter Automation, Digital Permission System, Parent Monitoring Dashboard, School Monitoring Dashboard, Smart Recommendation Engine.
- **Kebutuhan fungsional:** 40 fungsi (F-01 s.d. F-40).
- **Aturan poin (default):** Terlambat -2, Alpha -5, Merokok -30, Juara Lomba +20 (dapat dikonfigurasi tanpa ubah kode).
- **Aturan SP (default):** Poin ≤80 → SP1, ≤60 → SP2, ≤40 → SP3.
- **Recommendation Engine:** Rule-based murni (BUKAN ML/LLM). Alpha >5 → Konseling; Poin <70 → Pembinaan; Poin <50 → Pemanggilan Orang Tua.
- **Out of scope v1.0:** mobile native, CBT, E-Rapor, SPP, perpustakaan, inventaris, kepegawaian, Dapodik, WA/SMS gateway, fingerprint, server fisik, tablet absensi, hosting/domain.
- **Durasi:** 2 bulan sejak penandatanganan + data pendukung diterima.