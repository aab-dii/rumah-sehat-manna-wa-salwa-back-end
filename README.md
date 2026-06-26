# Rumah Sehat Manna wa Salwa — Backend API Server

[![Status](https://img.shields.io/badge/Status-Prototype%20%E2%80%94%20Local%20Only-orange)](#)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Repositori ini berisi kode sumber layanan backend API untuk sistem manajemen klinik **Rumah Sehat Manna wa Salwa**. Backend ini bertindak sebagai server penyedia data, pengelola transaksi janji temu, dan notifikasi real-time untuk aplikasi mobile.

---

## 🌟 Fitur-Fitur Utama Backend API
Berikut adalah detail fungsionalitas dan fitur teknis yang diimplementasikan pada sistem backend Laravel:

### 1. Sistem Autentikasi Hybrid (Firebase Auth & Laravel Sanctum)
* **Verifikasi Token Firebase:** Mengamankan akses API mobile dengan menerima dan memverifikasi Firebase ID Token dari aplikasi Android, kemudian menukarkannya dengan token akses Laravel Sanctum yang aman.
* **Role-Based Access Control (RBAC):** Otentikasi dan otorisasi terpadu berdasarkan peran pengguna: `pasien` (patient), `terapis` (therapist), `admin`, dan `super_admin`.

### 2. Manajemen Pengguna dengan Soft-Deletes
* **Pengelolaan Multi-Role:** CRUD data pengguna (admin, terapis, pasien) dengan skema keamanan tingkat tinggi.
* **Fitur Trash & Restore:** Menggunakan *Soft Deletes* (`deleted_at`) pada database, memungkinkan admin untuk menonaktifkan pengguna, melihat daftar sampah (trash), dan memulihkan kembali (restore) akun tanpa kehilangan riwayat data transaksi medis.

### 3. Manajemen Katalog Layanan Klinik
* **CRUD Layanan Interaktif:** Menyimpan data jenis terapi (seperti Bekam, Akupunktur, Ramuan) lengkap dengan nama, estimasi durasi, harga, dan status keaktifan.
* **Penanganan Multipart File:** API mendukung pengunggahan gambar ikon layanan secara aman ke storage lokal dan menyediakannya melalui URL publik.

### 4. Transaksi & Alur Reservasi (Booking) Terintegrasi
* **Pemesanan Mandiri & Jadwal Dinamis:** Pasien dapat memilih layanan, hari, jam slot, dan terapis berdasarkan ketersediaan jadwal terapis yang diproses secara dinamis.
* **Verifikasi Bukti Pembayaran:**
  * **Opsi Tunai (Cash):** Otomatis dikonfirmasi atau diproses langsung saat kedatangan.
  * **Opsi Transfer Bank:** Menampung unggahan foto bukti bayar dari pasien. Admin dapat memverifikasi transaksi (menerima atau menolak dengan mencantumkan alasan penolakan).
* **Auto-Cancellation Scheduler:** Logika terjadwal untuk membatalkan pesanan transfer bank secara otomatis jika pasien tidak mengunggah bukti pembayaran dalam kurun waktu 24 jam sejak pembuatan.
* **Force Complete:** Fitur admin untuk memaksa penyelesaian sesi janji temu yang terlewat diselesaikan oleh terapis agar pencatatan keuangan dan laporan tetap konsisten.

### 5. Logika Nomor Antrean Dinamis & Real-time Sync
* **Antrean Real-time per Hari per Terapis:** Nomor antrean dihitung secara dinamis dari database untuk setiap terapis per hari pelayanan.
* **Logika Tie-breaker Urutan Antrean:** Urutan ditentukan secara adil berdasarkan waktu janji temu (`booking_time` ASC). Jika ada waktu yang sama, *tie-breaker* ditentukan berdasarkan urutan waktu pemesanan dibuat (`created_at` ASC).
* **Pusher Event Broadcasting:** Memancarkan event pembaruan antrean dan status booking secara real-time ke aplikasi Android saat terjadi perubahan status janji temu.

### 6. Rekam Medis & Riwayat Terapi (Medical Records)
* **Pencatatan Klinis Terstruktur:** Terapis dapat menginput keluhan pasien, diagnosis klinis, titik bekam yang dikerjakan, ramuan herbal yang diresepkan, dan catatan evaluasi medis.
* **Middleware Pengamanan Ketat:** Mencegah akses silang (cross-access) antar terapis; hanya terapis yang ditugaskan pada booking tersebut yang berhak membuat atau menyunting rekam medis pasien.
* **Pencatatan Pasca Force Complete:** Transaksi yang di-force complete oleh admin tetap diwajibkan untuk diisi catatan terapinya oleh terapis yang bersangkutan melalui riwayat janji temu.

### 7. Engine Laporan Komprehensif & PDF Generator
* **DomPDF Integration:** Memanfaatkan library `barryvdh/laravel-dompdf` untuk mengubah template HTML Blade yang dirancang khusus menjadi berkas PDF standar cetak A4 Landscape.
* **Lima Jenis Laporan Utama:**
  1. **Laporan Keuangan:** Rincian pendapatan kotor, metode pembayaran (tunai/transfer), potongan/refund, serta ringkasan total pendapatan.
  2. **Laporan Kunjungan Terapis:** Data kunjungan lengkap dengan alamat klinik, penutupan STPT, dan pembagian jenis kelamin pasien (L/P).
  3. **Laporan Kinerja Terapis:** Statistik jumlah sesi pelayanan dan kontribusi pendapatan masing-masing terapis.
  4. **Laporan Kegiatan Klinik:** Grafik kontribusi persentase layanan terpopuler dengan representasi visual string block (`██████`).
  5. **Laporan Komparatif Performa:** Membandingkan kinerja antar terapis (hanya untuk Super Admin).
* **Hak Akses Laporan (RBAC):**
  * Terapis hanya dapat mengekspor laporan kinerja diri sendiri.
  * Admin dapat mengekspor laporan Keuangan, Kunjungan, Kinerja, dan Kegiatan.
  * Super Admin dapat mengekspor seluruh laporan termasuk Laporan Komparatif.

### 8. Sistem Push Notification Terintegrasi
* **Firebase Cloud Messaging (FCM):** Trigger otomatis pengiriman notifikasi ke perangkat HP Android pasien/terapis/admin pada setiap transisi status booking yang krusial (misal: bukti bayar ditolak, janji temu disetujui, rekam medis diisi, dll).

---

## 🖥️ Tech Stack
* **Framework:** Laravel 11
* **Bahasa Pemrograman:** PHP >= 8.2
* **Database:** MySQL / SQLite (untuk pengujian)
* **Real-time Event:** Pusher Channels
* **Notifikasi & Autentikasi:** Firebase Admin SDK (FCM & Firebase Auth)

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
