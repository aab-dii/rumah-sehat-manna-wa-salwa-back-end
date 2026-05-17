<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

/**
 * Sprint 2.1: Command untuk membuat akun Super Admin.
 * Membuat user di Firebase Auth + database Laravel.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super
                            {--email=superadmin@rumahsehat.com : Email super admin}
                            {--name=Super Admin : Nama super admin}
                            {--password=superadmin123 : Password}
                            {--phone=081200000000 : Nomor telepon}';

    protected $description = 'Buat akun Super Admin baru (Firebase + Database)';

    public function handle(FirebaseAuth $firebaseAuth): int
    {
        $email    = $this->option('email');
        $name     = $this->option('name');
        $password = $this->option('password');
        $phone    = $this->option('phone');

        $this->info('');
        $this->info('═══════════════════════════════════════');
        $this->info('   Membuat Akun Super Admin');
        $this->info('═══════════════════════════════════════');
        $this->info("  Email    : {$email}");
        $this->info("  Nama     : {$name}");
        $this->info("  Password : {$password}");
        $this->info("  Phone    : {$phone}");
        $this->info('═══════════════════════════════════════');

        // Cek apakah email sudah ada di database
        if (User::where('email', $email)->exists()) {
            $existing = User::where('email', $email)->first();

            if ($existing->role === 'super_admin') {
                $this->warn("⚠ Akun super_admin dengan email {$email} sudah ada.");
                return Command::SUCCESS;
            }

            // Upgrade role existing user ke super_admin
            if ($this->confirm("Akun dengan email {$email} sudah ada sebagai '{$existing->role}'. Upgrade ke super_admin?")) {
                $existing->update([
                    'role'      => 'super_admin',
                    'is_active' => true,
                ]);
                $this->info("✅ Role berhasil diupgrade ke super_admin!");
                return Command::SUCCESS;
            }

            $this->info('Dibatalkan.');
            return Command::FAILURE;
        }

        // Step 1: Buat akun di Firebase Auth
        $this->info('');
        $this->info('📌 Step 1: Membuat akun di Firebase Auth...');

        try {
            $firebaseUser = $firebaseAuth->createUserWithEmailAndPassword($email, $password);
            $firebaseUid  = $firebaseUser->uid;
            $this->info("   ✅ Firebase UID: {$firebaseUid}");
        } catch (\Kreait\Firebase\Exception\Auth\EmailExists $e) {
            // Kalau sudah ada di Firebase, ambil UID-nya
            $this->warn('   ⚠ Email sudah terdaftar di Firebase, mengambil UID...');
            try {
                $existingFirebaseUser = $firebaseAuth->getUserByEmail($email);
                $firebaseUid = $existingFirebaseUser->uid;
                $this->info("   ✅ Firebase UID (existing): {$firebaseUid}");
            } catch (\Exception $e2) {
                $this->error("   ❌ Gagal mengambil UID Firebase: {$e2->getMessage()}");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Gagal buat akun Firebase: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Step 2: Buat akun di database Laravel
        $this->info('');
        $this->info('📌 Step 2: Menyimpan ke database...');

        try {
            $user = User::create([
                'name'         => $name,
                'email'        => $email,
                'password'     => Hash::make($password),
                'role'         => 'super_admin',
                'phone_number' => $phone,
                'firebase_uid' => $firebaseUid,
                'is_active'    => true,
                'gender'       => 'L',
            ]);

            $this->info("   ✅ User ID: {$user->id}");
        } catch (\Exception $e) {
            $this->error("   ❌ Gagal simpan ke database: {$e->getMessage()}");
            $this->warn('   ⚠ Menghapus akun Firebase yang sudah dibuat...');
            try {
                $firebaseAuth->deleteUser($firebaseUid);
            } catch (\Exception $e2) {
                $this->error("   ❌ Gagal rollback Firebase: {$e2->getMessage()}");
            }
            return Command::FAILURE;
        }

        // Done
        $this->info('');
        $this->info('═══════════════════════════════════════');
        $this->info('   ✅ SUPER ADMIN BERHASIL DIBUAT!');
        $this->info('═══════════════════════════════════════');
        $this->info("  Email    : {$email}");
        $this->info("  Password : {$password}");
        $this->info("  Role     : super_admin");
        $this->info("  DB ID    : {$user->id}");
        $this->info("  Firebase : {$firebaseUid}");
        $this->info('═══════════════════════════════════════');
        $this->info('');
        $this->warn('⚠ Simpan password ini! Gunakan untuk login di aplikasi Android.');

        return Command::SUCCESS;
    }
}
