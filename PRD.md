## Attention here, dibawah ini system yang dirancang, jika code yang lebih better dengan arsitektur yang lebih baik bisa diimplementasikan maka sangat diperbolehkan, dibawah ini bisa menjadi acuan :


# PRD — SMARTSIS (Smart Student Intelligence System)
 
> **Product Requirements Document** untuk pengembangan platform monitoring & pembinaan siswa terintegrasi SMAN 5 Bekasi.
> Dokumen ini diturunkan dari Kerangka Acuan Kerja (KAK) v1.0 dan dipakai sebagai sumber kebenaran (single source of truth) untuk pengembangan, termasuk untuk AI-assisted development (Claude Code).
 
| | |
|---|---|
| **Produk** | SMARTSIS — Smart Student Intelligence System |
| **Klien** | SMAN 5 Bekasi |
| **Pengembang** | Sibermuda Indonesia |
| **Versi PRD** | 1.0 |
| **Versi Sistem** | 1.0 |
| **Status** | Draft Final |
| **Durasi** | ± 2 bulan sejak penandatanganan |
 
---
 
## 1. Ringkasan Produk (Product Overview)
 
SMARTSIS adalah aplikasi web terpusat untuk **monitoring, pembinaan, dan pengelolaan aktivitas kesiswaan** di SMAN 5 Bekasi. Sistem menggabungkan kehadiran, kredit poin kedisiplinan, pelanggaran, prestasi, perizinan, surat peringatan, serta monitoring orang tua ke dalam satu basis data terpusat dengan kontrol akses berbasis peran (RBAC).
 
### 1.1 Problem yang Diselesaikan
- Monitoring kehadiran belum optimal & rawan penyalahgunaan absensi.
- Data kedisiplinan (pelanggaran, pembinaan, poin) tersebar dan belum terintegrasi.
- Pengelolaan kredit poin masih manual dan rawan ketidaksesuaian data.
- Orang tua belum punya akses real-time terhadap aktivitas anak.
- Administrasi pembinaan (SP, laporan) memakan waktu.
- Belum ada analisis untuk mengidentifikasi siswa yang butuh perhatian khusus.
### 1.2 Tujuan Utama (Goals)
1. Mengintegrasikan seluruh aktivitas siswa ke satu sistem terpusat.
2. Digitalisasi absensi via Face Recognition + Blink Detection.
3. Otomatisasi kredit poin kedisiplinan (Dynamic Point Engine).
4. Penerbitan surat peringatan (SP) terstruktur & otomatis.
5. Perizinan digital dengan alur persetujuan.
6. Dashboard monitoring untuk sekolah & orang tua.
7. Rekomendasi pembinaan berbasis aturan (rule-based).
8. Mendukung pengambilan keputusan berbasis data.
### 1.3 Non-Goals (Tidak Termasuk v1.0)
Aplikasi Android/iOS native, CBT/Ujian online, E-Rapor, pembayaran SPP, perpustakaan, inventaris, kepegawaian, integrasi Dapodik, WhatsApp/SMS Gateway, integrasi fingerprint device, pengadaan server fisik, pengadaan tablet absensi, serta hosting & domain tahunan.
 
---
 
## 2. Stack Teknologi (Target Architecture)
 
Aplikasi ditransformasi/dibangun di atas stack berikut:
 
| Layer | Teknologi | Catatan |
|---|---|---|
| **Backend Framework** | Laravel 13 (versi stabil terbaru) | Business logic, auth, API internal |
| **Template Engine** | Blade | Server-side rendering |
| **Interaktivitas** | Livewire | Komponen interaktif tanpa SPA penuh |
| **JS Ringan** | AlpineJS | Interaksi UI ringan (toggle, dropdown, dsb.) |
| **Styling/UI** | Bootstrap (responsif) | *Opsional: Tailwind bila tim sudah terbiasa* |
| **Database** | MySQL | Penyimpanan utama |
| **Face Recognition** | Face-api.js (browser-based) | Registrasi, verifikasi, pencocokan wajah |
| **Liveness** | Blink Detection (Face-api.js) | Anti-spoofing menggunakan foto |
| **RBAC** | Spatie Laravel-Permission | 9 role + permission granular |
| **PDF** | barryvdh/laravel-dompdf (atau setara) | Cetak surat peringatan & laporan |
| **Export Excel** | maatwebsite/excel | Import/export data |
 
> **Catatan migrasi:** KAK menyebut Laravel 12 sebagai baseline; PRD ini menargetkan **Laravel 13** sesuai stack tim. Hindari paket yang belum kompatibel dengan Laravel 13 — verifikasi setiap dependency.
 
### 2.1 Arsitektur
Client–Server:
- **Client:** laptop/PC/tablet/smartphone via browser modern (Chrome direkomendasikan untuk Face-api.js).
- **Application Server:** Laravel (business logic, auth, authorization, internal API).
- **Database Server:** MySQL.
Face Recognition berjalan **di sisi browser** (Face-api.js); server hanya menyimpan *face descriptor/template* (vektor) dan mencocokkan/menyimpan hasilnya.
 
---
 
## 3. Pengguna & Hak Akses (RBAC)
 
Sistem memakai **Role Based Access Control** dengan 9 role utama (implementasi via Spatie Permission).
 
| # | Role | Fungsi Utama |
|---|------|--------------|
| 1 | Super Admin | Pengelola sistem & master data |
| 2 | Kepala Sekolah | Monitoring & evaluasi strategis |
| 3 | Wakasek Kesiswaan | Monitoring kesiswaan menyeluruh |
| 4 | Guru BK | Pembinaan, konseling, verifikasi pelanggaran, approval SP |
| 5 | Wali Kelas | Monitoring siswa per kelas (scoped) |
| 6 | Guru Piket | Monitoring operasional harian, input pelanggaran, approval izin |
| 7 | Guru Mapel | Akses baca terbatas (profil & kehadiran siswa) |
| 8 | Siswa | Lihat data pribadi, ajukan izin |
| 9 | Orang Tua/Wali | Monitoring data anak (scoped ke anak terkait) |
 
### 3.1 Matriks Hak Akses
 
| Modul | Admin | Kepsek | Wakasis | BK | Walas | Piket | Guru | Siswa | Ortu |
|-------|:-----:|:------:|:-------:|:--:|:-----:|:-----:|:----:|:-----:|:----:|
| Master Data | Kelola | – | – | – | – | – | – | – | – |
| Absensi | Kelola | Lihat | Lihat | Lihat | Lihat | Kelola | Lihat | Lihat | Lihat |
| Pelanggaran | Kelola | Lihat | Lihat | Kelola | Lihat | Input | Lihat | Lihat | Lihat |
| Poin | Kelola | Lihat | Lihat | Kelola | Lihat | Lihat | – | Lihat | Lihat |
| Prestasi | Kelola | Lihat | Lihat | Kelola | Lihat | – | – | Lihat | Lihat |
| Perizinan | Kelola | Lihat | Lihat | Lihat | Lihat | Kelola | – | Ajukan | Lihat |
| Surat Peringatan | Kelola | Lihat | Lihat | Kelola | Lihat | – | – | Lihat | Lihat |
| Dashboard | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
 
**Aturan scoping penting:**
- **Wali Kelas** hanya boleh mengakses siswa pada kelas yang diampu.
- **Orang Tua** hanya boleh mengakses data anak yang tertaut ke akunnya.
- **Siswa** tidak boleh mengakses data siswa lain.
- **Kepala Sekolah & Guru Mapel** bersifat read-only terhadap data operasional.
> Implementasi scoping disarankan via **Policy** + **Global Scope**/query filter, bukan sekadar cek role di view.
 
---
 
## 4. Model Data (Usulan Skema)
 
Skema indikatif untuk migrasi Laravel. Sesuaikan tipe & index saat implementasi.
 
| Tabel | Field Inti | Relasi |
|-------|-----------|--------|
| `users` | id, name, email, password, is_active | hasOne profil sesuai role |
| `roles` / `permissions` | (Spatie) | — |
| `students` | id, nis, nisn, name, gender, class_id, major_id, year_in, current_point | belongsTo class, major; hasMany attendances, violations, dsb. |
| `teachers` | id, user_id, name, nip | belongsTo user |
| `parents` | id, user_id, name, phone | belongsToMany students (pivot `parent_student`) |
| `classrooms` | id, name, major_id, homeroom_teacher_id (wali kelas) | belongsTo major, teacher |
| `majors` | id, name (jurusan) | hasMany classrooms |
| `academic_years` | id, name, is_active | — |
| `face_templates` | id, student_id, descriptor (JSON/BLOB), registered_at | belongsTo student |
| `attendances` | id, student_id, date, check_in, check_out, status (hadir/terlambat/izin/sakit/alpha) | belongsTo student |
| `point_rules` | id, category, code, point (+/-), is_active | — |
| `point_logs` | id, student_id, source_type, source_id, delta, balance_after, note, created_at | belongsTo student (polymorphic source) |
| `violation_categories` | id, name, default_point | — |
| `violations` | id, student_id, category_id, chronology, evidence_path, status, reported_by, verified_by | belongsTo student, category, teacher |
| `achievement_categories` | id, name, level, default_point | — |
| `achievements` | id, student_id, category_id, level, evidence_path, input_by | belongsTo student, category |
| `warning_rules` | id, level (SP1/2/3), threshold_point | — |
| `warning_letters` | id, student_id, level, status, approved_by, pdf_path, issued_at | belongsTo student |
| `permissions_requests` | id, student_id, type (terlambat/keluar/pulang_awal), reason, status, approved_by | belongsTo student |
| `recommendations` | id, student_id, rule_code, risk_level, message, generated_at | belongsTo student |
| `activity_logs` | id, user_id, action, subject_type, subject_id, ip, created_at | audit trail |
 
> `point_logs.source_type` mengikat ke pelanggaran/prestasi/keterlambatan/alpha → mendukung histori poin yang dapat ditelusuri.
 
---
 
## 5. Kebutuhan Fungsional (Functional Requirements)
 
40 kebutuhan fungsional wajib ada di v1.0. Dikelompokkan per modul/epic.
 
### Epic A — Autentikasi & Pengguna
- **F-01 Login** — semua 9 role login, diarahkan ke dashboard sesuai role.
- **F-02 Logout** — sesi berakhir aman, kembali ke halaman login.
- **F-03 Manajemen Pengguna** (Admin) — tambah, ubah, nonaktifkan akun, reset password.
### Epic B — Master Data
- **F-04 Data Siswa** — CRUD + import/export Excel. Field: NIS, NISN, nama, JK, kelas, jurusan, tahun masuk, nama ortu.
- **F-05 Data Guru** — CRUD.
- **F-06 Data Kelas** — CRUD.
- **F-07 Tahun Ajaran** — tambah, aktifkan, tutup.
### Epic C — Smart Attendance (Face-api.js)
- **F-08 Registrasi Wajah** (Admin) — buka kamera → ambil wajah → simpan descriptor → tautkan ke siswa.
- **F-09 Absensi Masuk** — baca wajah → cocokkan → minta kedip → catat hadir.
- **F-10 Absensi Pulang** — metode sama, catat status pulang.
- **F-11 Monitoring Kehadiran** — hadir/terlambat/izin/sakit/alpha.
### Epic D — Dynamic Point System
- **F-12 Konfigurasi Poin** (Admin) — atur aturan poin (mis. Terlambat −2, Alpha −5, Merokok −30, Juara Lomba +20).
- **F-13 Perubahan Poin Otomatis** — trigger dari pelanggaran, prestasi, keterlambatan, alpha.
- **F-14 Histori Poin** — simpan seluruh riwayat perubahan (audit).
### Epic E — Pelanggaran (Violation Management)
- **F-15 Input Pelanggaran** (Guru BK, Guru Piket) — data: siswa, jenis, kronologi, bukti foto.
- **F-16 Verifikasi Pelanggaran** (Guru BK) — setujui/tolak/revisi.
- **F-17 Histori Pelanggaran** — seluruh riwayat per siswa.
### Epic F — Prestasi (Achievement)
- **F-18 Input Prestasi** (BK, Wali Kelas, Admin) — jenis, tingkat, bukti.
- **F-19 Penambahan Poin Prestasi** — otomatis berdasarkan prestasi terverifikasi.
### Epic G — Surat Peringatan (Warning Letter Automation)
- **F-20 Konfigurasi SP** (Admin) — ambang batas (mis. ≤80 → SP1, ≤60 → SP2, ≤40 → SP3).
- **F-21 Rekomendasi SP Otomatis** — deteksi siswa yang memenuhi syarat.
- **F-22 Persetujuan SP** (Guru BK) — setujui/tolak.
- **F-23 Cetak SP** — output PDF SP1/SP2/SP3.
### Epic H — Perizinan (Digital Permission)
- **F-24 Pengajuan Izin** (Siswa) — izin terlambat/keluar/pulang awal.
- **F-25 Persetujuan Izin** (Guru Piket, Wali Kelas).
- **F-26 Histori Izin** — seluruh riwayat per siswa.
### Epic I — Dashboard Orang Tua
- **F-27 Monitoring Kehadiran Anak** — hadir/terlambat/alpha.
- **F-28 Monitoring Poin** — poin saat ini + histori.
- **F-29 Monitoring Pelanggaran** — daftar + bukti.
- **F-30 Monitoring Surat Peringatan** — SP yang diterbitkan.
### Epic J — Dashboard Sekolah
- **F-31 Dashboard Kepala Sekolah** — statistik siswa, absensi, pelanggaran, poin.
- **F-32 Dashboard Wakasek Kesiswaan** — monitoring kedisiplinan/pelanggaran/keterlambatan.
- **F-33 Dashboard Guru BK** — kasus aktif, rekomendasi pembinaan, siswa berisiko.
### Epic K — Smart Recommendation Engine (Rule-Based)
- **F-34 Analisis Aktivitas** — parameter: kehadiran, keterlambatan, pelanggaran, poin.
- **F-35 Rekomendasi Pembinaan** — output saran tindak lanjut.
- **F-36 Deteksi Risiko** — Rendah / Sedang / Tinggi.
### Epic L — Laporan & Export
- **F-37 Export Absensi** — PDF & Excel.
- **F-38 Export Pelanggaran** — PDF & Excel.
- **F-39 Export Poin** — PDF & Excel.
- **F-40 Export Perizinan** — PDF & Excel.
---
 
## 6. Logika Bisnis Inti (Engines)
 
### 6.1 Dynamic Point Engine
- Setiap aktivitas (pelanggaran/prestasi/keterlambatan/alpha) memicu pencarian `point_rules`.
- Hitung delta → update `students.current_point` → tulis `point_logs` (delta + balance_after + sumber).
- Aturan dapat diubah dari panel admin **tanpa ubah kode**.
- Implementasi disarankan: **Event → Listener** (mis. `ViolationVerified` → `ApplyPointAdjustment`) agar mudah ditelusuri & diuji.
### 6.2 Smart Recommendation Engine (Rule-Based, BUKAN ML/LLM)
Murni logika aturan yang dikonfigurasi sekolah. Contoh rule:
 
| Kondisi | Aksi / Output |
|---------|---------------|
| Alpha ≥ 5 kali | Rekomendasi Konseling |
| Poin ≤ 70 | Rekomendasi Pembinaan |
| Poin ≤ 50 | Rekomendasi Pemanggilan Orang Tua |
 
Output level risiko: Rendah / Sedang / Tinggi. Disarankan dieksekusi via **scheduled job** (mis. harian) + on-demand di dashboard BK.
 
> Catatan: KAK menegaskan engine ini **tidak** memakai machine learning atau LLM. Tetap rule-based agar ringan & dapat dikonfigurasi.
 
### 6.3 Smart Attendance (Face-api.js)
Alur: datang → kamera baca wajah → Face Recognition → minta kedip → Blink Detection validasi → cocokkan template → catat waktu → tentukan status → simpan. Status keluaran: Hadir / Terlambat / Tidak Terdaftar. Keterlambatan dihitung dari jam masuk yang dikonfigurasi.
 
---
 
## 7. Alur Bisnis Ringkas (Integrasi Antar Modul)
 
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
 
Semua modul berbagi satu basis data terpusat sehingga aktivitas siswa termonitor berkelanjutan.
 
---
 
## 8. Kebutuhan Non-Fungsional
 
### 8.1 Keamanan
- Autentikasi wajib untuk semua role.
- Otorisasi berbasis role + policy (scoping wali kelas & orang tua).
- Password di-hash (bcrypt/argon — default Laravel).
- Activity log untuk audit aktivitas penting.
### 8.2 Performa & Kebutuhan Server (Minimum)
| Komponen | Spesifikasi |
|---|---|
| CPU | 4 Core |
| RAM | 8 GB |
| Storage | 100 GB SSD |
| OS | Ubuntu Server |
| Database | MySQL |
| PHP | 8.3+ |
 
### 8.3 Perangkat Absensi (disediakan sekolah)
Tablet/Smartphone Android atau laptop berkamera. Minimum: kamera HD, RAM 4 GB, Chrome terbaru.
 
### 8.4 Backup & Pemeliharaan
- Backup DB harian & mingguan, restore saat dibutuhkan.
- Maintenance: perbaikan bug, penyesuaian minor, monitoring performa.
---
 
## 9. Kriteria Penerimaan (Acceptance Criteria)
 
| # | Indikator |
|---|-----------|
| 1 | Sistem dapat dipakai semua role sesuai hak akses |
| 2 | Absensi siswa berfungsi via Face Recognition |
| 3 | Data kehadiran tersimpan otomatis |
| 4 | Sistem kredit poin berjalan sesuai konfigurasi |
| 5 | Pelanggaran siswa tercatat dengan baik |
| 6 | Surat peringatan dapat dihasilkan sistem (PDF) |
| 7 | Orang tua dapat memantau aktivitas anak |
| 8 | Dashboard monitoring dapat dipakai pihak sekolah |
| 9 | Laporan dapat dihasilkan sesuai kebutuhan |
| 10 | Sistem dapat dipakai dalam operasional sekolah |
 
---
 
## 10. Deliverables & Timeline
 
### 10.1 Deliverables
1. Aplikasi SMARTSIS v1.0
2. Database sistem
3. Source code aplikasi
4. Dokumen KAK
5. Panduan penggunaan
6. Dokumen serah terima
7. Garansi sesuai kesepakatan
### 10.2 Fase Pelaksanaan (± 2 bulan)
1. Analisis kebutuhan
2. Perancangan sistem
3. Pengembangan sistem
4. Pengujian (fungsi, hak akses, absensi wajah, laporan)
5. Implementasi (konfigurasi app/DB/pengguna)
6. Pelatihan pengguna (Kepsek, Wakasis, BK, Wali Kelas, Admin)
7. Serah terima
### 10.3 Saran Urutan Pengembangan (untuk transformasi bertahap)
1. **Fondasi:** Auth + RBAC (Spatie) + Master Data (Epic A & B).
2. **Inti kedisiplinan:** Point Engine + Pelanggaran + Prestasi (Epic D, E, F).
3. **Absensi:** Smart Attendance + Monitoring Kehadiran (Epic C).
4. **Pembinaan:** Surat Peringatan + Recommendation Engine (Epic G, K).
5. **Layanan:** Perizinan (Epic H).
6. **Visibilitas:** Dashboard Sekolah & Orang Tua + Export (Epic I, J, L).
---
 
## 11. Catatan Perubahan Ruang Lingkup
Perubahan ruang lingkup setelah pengembangan berjalan dikategorikan pekerjaan tambahan dan dituangkan dalam **Change Request / addendum** yang disepakati kedua pihak (penyesuaian waktu/biaya bila perlu).