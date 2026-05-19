# SPRINT REPORT MASTER: RUMAH SEHAT MANNA WA SALWA
Aplikasi Manajemen Klinik Berbasis Android + Laravel

Dokumen ini merangkum seluruh perjalanan pengembangan sistem manajemen klinik *Rumah Sehat Manna wa Salwa*, mencakup detail teknis, basis data, implementasi logika, dan desain antarmuka dari **Sprint 1.1** hingga **Sprint 2.4**.

---

## 📋 Daftar Isi
1. [Sprint 1.1: Manajemen Layanan Klinik](#sprint-11-manajemen-layanan-klinik)
2. [Sprint 1.2: Manajemen Pengguna & Otorisasi](#sprint-12-manajemen-pengguna--otorisasi)
3. [Sprint 1.3: Penjadwalan & Transaksi Booking](#sprint-13-penjadwalan--transaksi-booking)
4. [Sprint 2.1: Rekam Terapi (Medical Records)](#sprint-21-rekam-terapi-medical-records)
5. [Sprint 2.2: Sistem Notifikasi FCM & Dashboard](#sprint-22-sistem-notifikasi-fcm--dashboard)
6. [Sprint 2.3: Nomor Antrian Dinamis & Pengamanan Visual Timeout](#sprint-23-nomor-antrian-dinamis--pengamanan-visual-timeout)
7. [Sprint 2.4: Sistem Laporan Komprehensif & Ekspor PDF](#sprint-24-sistem-laporan-komprehensif--ekspor-pdf)

---

## 1. Sprint 1.1: Manajemen Layanan Klinik
* **Fokus**: Implementasi sistem manajemen layanan (Bekam, Akupunktur, Ramuan) yang ditawarkan oleh klinik.
* **Backend (Laravel)**:
  * Migrasi tabel `services`: nama layanan, deskripsi, durasi estimasi, harga, status aktif, dan path gambar ikon.
  * API CRUD lengkap dengan validasi request pada `ServiceController`.
  * Penanganan unggahan berkas gambar (multipart) untuk ikon/layanan.
* **Frontend (Android)**:
  * Refaktor dari sistem Paging3 ke pemuatan list sederhana (`ServiceRepository` & `ServiceViewModel`) untuk meminimalkan beban memori karena variasi layanan sedikit.
  * Desain UI Manajemen Layanan untuk Admin (Tambah, Edit, Detail, Hapus) menggunakan komponen Jetpack Compose premium.
  * Integrasi *Image Picker* dan pengiriman berkas via `MultipartBody.Part` ke API Laravel.

## 2. Sprint 1.2: Manajemen Pengguna & Otorisasi
* **Fokus**: Pembagian akses pengguna berdasarkan peran (*Role-based Access Control*) dan manajemen daftarnya.
* **Backend (Laravel)**:
  * Migrasi tabel `users` dengan kolom peran/role: `pasien`, `terapis`, `admin`, `super_admin`.
  * Integrasi Firebase Authentication dengan Laravel Sanctum. Backend memverifikasi Firebase ID Token untuk membuat token sesi lokal.
  * Penambahan fitur *Soft Delete* (tabel `users` memiliki kolom `deleted_at`).
  * API untuk melihat daftar user berdasarkan role, detail user, pembuatan akun terapis/admin baru, serta pemulihan dari Trash (*Restore*).
* **Frontend (Android)**:
  * Antarmuka pengelola pengguna: `AdminManageUsersScreen`, `AdminUserDetailScreen`, `AdminEditUserScreen`.
  * Filter visual antara data aktif dan data terhapus (*trash*).
  * Pembuatan dropdown dinamis terapis di form penjadwalan dan formulir pendaftaran.

## 3. Sprint 1.3: Penjadwalan & Transaksi Booking
* **Fokus**: Alur reservasi layanan oleh pasien, manajemen jadwal terapis, dan verifikasi pembayaran oleh admin.
* **Backend (Laravel)**:
  * Migrasi tabel `bookings` dan `schedules`. Tabel `bookings` menampung status: `pending`, `confirmed`, `in_progress`, `completed`, `canceled`.
  * Logika auto-cancel reservasi dengan metode transfer bank jika bukti bayar tidak diunggah dalam waktu 24 jam.
  * Verifikasi pembayaran manual oleh admin (terima untuk mengubah status menjadi `confirmed` atau tolak dengan melampirkan alasan penolakan).
* **Frontend (Android)**:
  * Halaman booking pasien: memilih layanan, tanggal, jam, terapis, hingga unggah bukti bayar (jika transfer).
  * Filter dinamis janji temu berdasarkan status pada halaman `AdminAppointmentScreen` dan `PatientAppointmentScreen`.
  * Navigasi pintas dari widget dashboard menuju daftar antrean verifikasi pembayaran.

## 5. Sprint 2.1: Rekam Terapi (Medical Records)
* **Fokus**: Pencatatan diagnosis, keluhan, dan tindakan medis (bekam/akupunktur/ramuan) setelah janji temu selesai.
* **Backend (Laravel)**:
  * Migrasi tabel `therapy_records` berelasi dengan `bookings` dan `patients`.
  * Kolom vital: keluhan utama, diagnosis, titik bekam yang digunakan, jenis ramuan yang diresepkan, catatan terapis, dan status rekam medis.
  * Middleware pengamanan: rekam medis hanya boleh diisi/diubah oleh terapis yang melayani janji temu tersebut.
* **Frontend (Android)**:
  * UI Formulir Rekam Terapi bagi Terapis (`TherapyRecordFormScreen`).
  * Tampilan rekam medis pada sisi Pasien (`PatientTherapyRecord`) untuk melihat riwayat perawatan mereka secara kronologis.

## 6. Sprint 2.2: Sistem Notifikasi FCM & Dashboard
* **Fokus**: Notifikasi instan menggunakan Firebase Cloud Messaging (FCM) dan visualisasi ringkasan data di dashboard.
* **Backend (Laravel)**:
  * Integrasi SDK Firebase Admin untuk pengiriman push notification.
  * Trigger otomatis notifikasi ketika: janji temu baru dibuat, bukti bayar diverifikasi/ditolak, rekam medis diisi, atau status diubah terapis.
  * Penghitungan metrik dashboard: pendapatan bulan berjalan, total verifikasi tertunda, statistik harian terapis.
* **Frontend (Android)**:
  * Background Service untuk menangkap FCM Payload dan menampilkan notifikasi sistem Android.
  * Sinkronisasi navigasi: mengetuk notifikasi verifikasi langsung membuka halaman detail janji temu yang bersangkutan.

## 7. Sprint 2.3: Nomor Antrian Dinamis & Pengamanan Visual Timeout
* **Fokus**: Pemberian nomor antrian dinamis (*real-time*) per hari per terapis, serta perbaikan bug visual auto-cancel.
* **Backend (Laravel)**:
  * Kalkulasi antrian dinamis pada endpoint detail booking. Urutan dihitung berdasarkan waktu janji temu (`booking_time` ASC).
  * **Logika Tie-breaker**: Jika dua booking memiliki jam yang sama, urutan ditentukan berdasarkan waktu pembuatan booking (`created_at` ASC).
* **Frontend (Android)**:
  * Desain komponen `QueueInfoCard` dengan visual mencolok (*GreenPrimary* dan *GreenSoft*) untuk pasien.
  * **Resolusi Bug Kritis (BUG-09)**: Memperbaiki bug visual di mana penghitung mundur (countdown) waktu transfer salah mengeksekusi status visual menjadi `"Dibatalkan"` untuk semua janji temu (termasuk yang cash atau sudah bayar) ketika sisa detik menyentuh 0. Ditambahkan validasi ketat status awal (`pending`, `unpaid`, `transfer`).

## 8. Sprint 2.4: Sistem Laporan Komprehensif & Ekspor PDF
* **Fokus**: Sistem pelaporan klinis (Keuangan, Kunjungan, Kinerja Terapis, Aktivitas Klinik, Komparatif) dan ekspor cetak PDF berstandar A4 Landscape.
* **Backend (Laravel)**:
  * Integrasi library `barryvdh/laravel-dompdf` untuk ekspor HTML ke file PDF.
  * Pembuatan Blade Template Premium dengan Kop Surat Resmi Klinik, tabel laporan rapi, ringkasan metrik, grafik contribution bar teks (`██████`), dan kolom tanda tangan pimpinan.
  * Implementasi *Role-Based Access Control (RBAC)*:
    * **Terapis**: Laporan Kunjungan Bulanan & Kinerja Diri (hanya data milik sendiri).
    * **Admin**: Laporan Keuangan, Kegiatan Klinik, dan Kinerja Semua Terapis.
    * **Super Admin**: Akses Admin + Laporan Komparatif Performa Terapis.
* **Frontend (Android)**:
  * Konfigurasi Retrofit `@Streaming` untuk mengunduh byte stream PDF tanpa memicu *Out of Memory (OOM)*.
  * Integrasi utility `PdfGenerator` yang memanfaatkan Android `MediaStore` API untuk menulis byte stream secara lokal ke direktori `/Downloads/MannaWaSalwa/`.
  * **Halaman Laporan Admin (`ReportScreen`)**: UI multi-tab (Keuangan, Kunjungan, Kinerja, Kegiatan, Komparatif) dengan layout kartu responsif, filter tanggal picker, filter dropdown terapis (pada tab Kunjungan), grafik linear progress, dan tombol ekspor PDF.
  * **Halaman Laporan Terapis (`TherapistReportScreen`)**: UI khusus terapis untuk memantau aktivitas bulanan dan performa pendapatan mereka pribadi.

---
*Seluruh fitur dari Sprint 1.1 hingga 2.4 telah berhasil diintegrasikan, lolos uji build otomatis, dan siap digunakan untuk kebutuhan produksi maupun presentasi.*
