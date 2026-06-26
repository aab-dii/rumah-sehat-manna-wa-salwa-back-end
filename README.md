# Rumah Sehat Manna wa Salwa — Backend API Server

[![Status](https://img.shields.io/badge/Status-Prototype%20%E2%80%94%20Local%20Only-orange)](#)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Repositori ini berisi kode sumber layanan backend API untuk sistem manajemen klinik **Rumah Sehat Manna wa Salwa**. Backend ini bertindak sebagai server penyedia data, pengelola transaksi janji temu, dan notifikasi real-time untuk aplikasi mobile.

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
