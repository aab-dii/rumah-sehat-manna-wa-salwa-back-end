# Rumah Sehat Manna wa Salwa — Backend API Server

[![Status](https://img.shields.io/badge/Status-Prototype%20%E2%80%94%20Local%20Only-orange)](#)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Repositori ini berisi kode sumber layanan backend API untuk sistem manajemen klinik **Rumah Sehat Manna wa Salwa**. Backend ini bertindak sebagai server penyedia data, pengelola transaksi janji temu, dan notifikasi real-time untuk aplikasi mobile.

---

## 🚀 Fitur Utama Aplikasi (Berdasarkan Modul)

Sistem **Rumah Sehat Manna wa Salwa** memiliki fitur utama yang terbagi ke dalam 5 modul fungsional sesuai dengan dokumentasi resmi proyek:

### Modul 1: Autentikasi & Manajemen Akun (M-01)
* **Registrasi Akun Mandiri:** API endpoint `/api/register` untuk pendaftaran pasien baru dengan validasi data nomor handphone, format email, dan batas keamanan sandi (8-64 karakter sesuai standar OWASP).
* **Login Multi-Metode:** Mendukung autentikasi konvensional menggunakan email & password, serta login terintegrasi **Google Sign-In** dengan memverifikasi Firebase ID Token di backend.
* **Penyimpanan Sesi Aman:** Penerbitan session token aman menggunakan **Laravel Sanctum** setelah token Firebase atau kredensial divalidasi.
* **Manajemen Profil:** REST API untuk memperbarui foto profil (penyimpanan file di local storage), mengubah informasi kontak (No. HP hanya menerima input angka), alamat, pekerjaan, serta memperbarui kata sandi secara aman.
* **Manajemen Pengguna (Admin & Super Admin - Filament v4):** 
  * Dashboard web admin berbasis **Filament v4** untuk melihat daftar seluruh pengguna klinik yang terurut secara alfabetis (A-Z).
  * Pembuatan akun Pasien, Terapis, dan Admin baru secara manual dari panel administrator.
  * Penonaktifan sementara (*deactivation/soft delete*) akun staf atau pasien menggunakan fitur *Soft Deletes* Laravel (`deleted_at`).
  * Reset kata sandi darurat yang secara otomatis menghasilkan password acak aman 12 karakter.

### Modul 2: Reservasi Terapi & Pengelolaan Transaksi (M-02)
* **Booking Layanan Terapi (Oleh Pasien):** API untuk menghitung slot waktu operasional terapis yang kosong secara dinamis, mencegah konflik jadwal, dan menerima booking dari pasien.
* **Pembuatan Booking oleh Admin & Super Admin:** Admin atau Super Admin dapat mendaftarkan janji temu baru atas nama Pasien secara langsung melalui Panel Admin Filament. Reservasi ini otomatis berstatus terkonfirmasi (**`confirmed`**) tanpa melewati alur verifikasi bukti pembayaran.
* **Pemisahan Alur Pembayaran:**
  * **Metode Tunai (Cash):** Transaksi dibuat dengan status pembayaran awal *unpaid*, dan janji temu divalidasi oleh admin untuk masuk ke agenda.
  * **Metode Transfer Bank:** Membatasi waktu unggah bukti bayar (maksimal 24 jam setelah booking dibuat atau 1 jam sebelum terapi dimulai). Menampung unggahan foto bukti transfer bank ke server.
* **Verifikasi Pembayaran & Antrean Dinamis:**
  * Panel admin untuk memvalidasi bukti pembayaran transfer. Menyetujui mengubah status transaksi menjadi *paid*, booking terkonfirmasi (*confirmed*), dan nomor urut antrean harian dihitung secara otomatis.
  * Menolak pembayaran akan mengubah status menjadi *rejected* disertai kolom catatan alasan penolakan, memicu notifikasi agar pasien mengunggah ulang bukti pembayaran.
* **Pencegahan Bentrok Jadwal (Double Booking Prevention):**
  * Logika database menggunakan penguncian baris (*pessimistic locking* `lockForUpdate()`) untuk mencegah terapis melayani dua pasien berbeda di jam yang sama (*Double Booking Terapis*).
  * Validasi database untuk melarang pasien memesan dua terapi yang bertabrakan di waktu yang sama (*Double Booking Pasien*).
* **Pengelolaan Operasional (Terapis):**
  * Terapis dapat mengatur jadwal mingguan rutin dan jam aktif praktik.
  * Fitur pengelolaan hari libur/cuti terapis untuk mengunci kalender pemesanan pasien.
  * Prosedur **Emergency Close (Tutup Darurat)** di backend untuk membatalkan seluruh antrean aktif hari ini dan mengunci sisa slot secara instan saat terapis mengalami kendala mendesak.
* **Integrasi Komunikasi WhatsApp:** Menyediakan data nomor WhatsApp admin/terapis/pasien secara terformat agar aplikasi klien dapat membuka link komunikasi chat WhatsApp secara langsung.

### Modul 3: Rekam Medis Digital (M-03)
* **Pencatatan Klinis Terapis:** Endpoint API untuk terapis mengisi keluhan pasien, diagnosis, tindakan terapi yang diberikan, dan catatan tambahan. Pengisian ini secara otomatis mengubah status janji temu menjadi selesai (`completed`).
* **Catatan Medis Susulan (Force Completed):** Memungkinkan pengisian catatan medis susulan untuk janji temu yang ditutup sepihak oleh admin (*force completed*). Sesi ini tetap muncul pada riwayat terapis dan wajib dilengkapi dengan rekam medis.
* **Riwayat Rekam Medis Pasien:** API untuk menyajikan riwayat pengobatan dan detail rekam medis dari kunjungan-kunjungan sebelumnya secara lengkap berdasarkan hak akses (pasien melihat miliknya sendiri, terapis melihat pasien yang ia tangani).
* **Filter Riwayat Medis:** Penyaringan catatan medis lama berdasarkan jenis layanan terapi yang pernah diambil.

### Modul 4: Notifikasi & Komunikasi Real-time (M-04)
* **Push Notification Firebase:** Menggunakan `kreait/laravel-firebase` SDK untuk mengirim push notification otomatis ke HP pengguna saat status janji temu berubah (pembayaran dikonfirmasi/ditolak, sesi dimulai/selesai, pembatalan).
* **Deep-Link Payload:** Menyematkan data payload detail booking (`booking_id` dan `role`) pada payload notifikasi FCM agar aplikasi Android dapat melakukan navigasi langsung ke halaman detail yang sesuai.

### Modul 5: Modul Laporan & Analitik Keuangan (M-05)
* **Laporan Laba Rugi Otomatis (Manna Sheet):** Kalkulasi backend untuk pendapatan kotor, pengembalian dana (*refund* akibat pembatalan), dan total laba bersih klinik secara otomatis dan akurat setiap bulan.
* **Tren Kunjungan:** Data statistik grafik jumlah kunjungan pasien di klinik setiap bulannya.
* **Analisis Kinerja Terapis:** Metrik penilai produktivitas (jumlah sesi pelayanan) bagi masing-masing terapis.
* **Ekspor Laporan PDF:** Engine PDF berbasis `barryvdh/laravel-dompdf` untuk menghasilkan file laporan keuangan bulanan (format lanskap A4) dan laporan kunjungan pasien secara dinamis langsung dari server.

---

## 🖥️ Tech Stack
* **Framework Utama:** Laravel 12 (PHP ^8.2)
* **Autentikasi API:** Laravel Sanctum (token-based authentication)
* **Database:** MySQL / PostgreSQL
* **Admin Panel / Back-office:** Filament v4 (Dashboard Administrasi yang tangguh dan responsif)
* **Integrasi Firebase (Server-Side):**
  * `kreait/laravel-firebase` untuk sinkronisasi data user & push notification
  * `google/auth` untuk otorisasi API Google
* **Ekspor Dokumen:** `barryvdh/laravel-dompdf` untuk pembuatan laporan PDF secara otomatis
* **Pengujian & Validasi:** PHPUnit (pengujian integrasi otomatis)
* **Web Server & Dev Tools:** Vite, Laravel Sail, Concurrently (menjalankan queue, server, log, dan frontend bersamaan)

---

## ⚙️ Prasyarat Sistem
Sebelum memulai instalasi, pastikan lingkungan Anda sudah terinstal tools berikut:
* **PHP** (Versi 8.2 atau lebih baru)
* **Composer** (Dependency manager PHP)
* **Node.js & NPM** (Untuk membangun aset frontend/admin panel)
* **MySQL** (Untuk database lokal)

---

## 🛠️ Langkah Instalasi & Setup Lokal

### 1. Unduh Kode Sumber
Kloning repositori ini ke komputer lokal Anda:
```bash
git clone https://github.com/aab-dii/rumah-sehat-manna-wa-salwa-back-end.git
cd rumah-sehat-manna-wa-salwa-back-end
```

### 2. Instalasi Dependencies
Jalankan perintah berikut untuk menginstal package PHP dan Javascript:
```bash
composer install
npm install && npm run build
```

### 3. Konfigurasi Environment File
Salin file `.env.example` menjadi `.env`:
```bash
copy .env.example .env
```
Buka file `.env` di text editor Anda, kemudian sesuaikan variabel-variabel berikut:

#### A. Database
Sesuaikan kredensial database lokal MySQL Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_rumah_sehat
DB_USERNAME=root
DB_PASSWORD=
```

#### B. Pusher (Real-time Booking)
Masukkan kredensial aplikasi Pusher Anda untuk mendukung fungsionalitas update real-time pada antrean:
```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=your-pusher-app-cluster
```

#### C. Firebase Service Account JSON (SANGAT PENTING)
Backend ini memverifikasi token masuk pasien serta mengirimkan push notification via FCM menggunakan SDK Admin Firebase.
1. Masuk ke **Firebase Console** proyek Anda.
2. Navigasi ke **Project Settings** > **Service Accounts**.
3. Ketuk tombol **Generate New Private Key**, lalu unduh berkas JSON.
4. Buat folder baru di proyek backend Anda pada jalur: `storage/app/firebase/` (jalur ini sudah aman di dalam `.gitignore` sehingga tidak akan terunggah ke repositori online).
5. Letakkan berkas JSON tersebut ke dalam folder tersebut dan beri nama. Contoh: `rumah-sehat-firebase-adminsdk.json`.
6. Tuliskan path file tersebut pada file `.env` Anda:
   ```env
   FIREBASE_CREDENTIALS=storage/app/firebase/rumah-sehat-firebase-adminsdk.json
   ```

### 4. Inisialisasi Application Key
Jalankan perintah berikut untuk men-generate key enkripsi Laravel:
```bash
php artisan key:generate
```

### 5. Migrasi & Seeding Database
Jalankan migrasi tabel sekaligus memasukkan data dummy (seeding) untuk akun uji coba:
```bash
php artisan migrate:fresh --seed
```
*Perintah ini akan secara otomatis membuat struktur database, tabel transaksi, riwayat medis, serta men-seed data akun demo (Pasien, Terapis, Admin, Super Admin) ke database lokal.*

### 6. Menjalankan Server Lokal
Jalankan server pengembangan Laravel agar dapat diakses dari luar localhost (oleh HP Android Anda):
```bash
php artisan serve --host=0.0.0.0 --port=8000
```
*Opsi `--host=0.0.0.0` memastikan server menerima koneksi dari perangkat luar dalam satu jaringan nirkabel (Wi-Fi) yang sama.*

---

## 🔗 Link Repositori Terkait
* **Frontend Android App:** [rumah-sehat-manna-wa-salwa (Android App)](https://github.com/aab-dii/rumah-sehat-manna-wa-salwa)
