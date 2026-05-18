<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

/**
 * Sprint 2.1: Controller khusus Super Admin.
 * Mengelola akun admin, toggle aktif/nonaktif, dan reset password.
 */
class SuperAdminController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    // =========================================================================
    // 1. LIST ADMIN
    // =========================================================================

    /**
     * GET /api/super-admin/admins
     * Tampilkan semua akun admin (termasuk super_admin).
     */
    public function index(Request $request)
    {
        $admins = User::admins()
            ->select('id', 'name', 'email', 'role', 'phone_number', 'is_active', 'last_active_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseFormatter::success($admins, 'Data admin berhasil diambil');
    }

    // =========================================================================
    // 2. CREATE ADMIN
    // =========================================================================

    /**
     * POST /api/super-admin/admins
     * Buat akun admin baru via Firebase + database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'     => ['required', 'string', 'min:8'],
            'no_hp'        => ['required', 'string', 'max:20'],
        ]);

        $firebaseUser = null;

        try {
            // 1. Buat akun Firebase
            $firebaseUser = $this->firebaseAuth->createUser([
                'email'       => $request->email,
                'password'    => $request->password,
                'displayName' => $request->nama_lengkap,
            ]);

            // 2. Simpan ke database
            $user = User::create([
                'name'         => $request->nama_lengkap,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'phone_number' => $request->no_hp,
                'role'         => 'admin', // Hanya bisa buat admin, bukan super_admin
                'firebase_uid' => $firebaseUser->uid,
                'is_active'    => true,
            ]);

            return ResponseFormatter::success($user, 'Akun admin berhasil dibuat');

        } catch (\Exception $e) {
            // Rollback: hapus akun Firebase jika database gagal
            if ($firebaseUser) {
                try {
                    $this->firebaseAuth->deleteUser($firebaseUser->uid);
                } catch (\Exception $rollbackError) {
                    Log::error('Firebase Rollback Failed: ' . $rollbackError->getMessage());
                }
            }

            Log::error('Create Admin Failed: ' . $e->getMessage());
            return ResponseFormatter::error(
                ['error' => $e->getMessage()],
                'Gagal membuat akun admin. Silakan coba lagi.',
                500
            );
        }
    }

    // =========================================================================
    // 3. UPDATE ADMIN
    // =========================================================================

    /**
     * PUT /api/super-admin/admins/{id}
     * Edit data admin. Tidak boleh edit diri sendiri & tidak boleh ubah role super_admin.
     */
    public function update(Request $request, $id)
    {
        $admin = User::admins()->find($id);

        if (!$admin) {
            return ResponseFormatter::error(null, 'Admin tidak ditemukan', 404);
        }

        // Tidak boleh edit diri sendiri via endpoint ini
        if ($admin->id === Auth::id()) {
            return ResponseFormatter::error(null, 'Tidak dapat mengedit akun Anda sendiri melalui panel ini. Gunakan menu profil.', 403);
        }

        // Tidak boleh mengubah role super_admin
        if ($admin->isSuperAdmin()) {
            return ResponseFormatter::error(null, 'Tidak dapat mengedit akun Super Admin.', 403);
        }

        $request->validate([
            'nama_lengkap' => ['sometimes', 'string', 'max:255'],
            'no_hp'        => ['sometimes', 'string', 'max:20'],
            'email'        => ['sometimes', 'email', 'unique:users,email,' . $id],
        ]);

        if ($request->has('nama_lengkap')) $admin->name = $request->nama_lengkap;
        if ($request->has('no_hp'))        $admin->phone_number = $request->no_hp;
        if ($request->has('email'))        $admin->email = $request->email;

        $admin->save();

        return ResponseFormatter::success($admin, 'Data admin berhasil diperbarui');
    }

    // =========================================================================
    // 4. TOGGLE ACTIVE
    // =========================================================================

    /**
     * POST /api/super-admin/admins/{id}/toggle-active
     * Aktifkan/nonaktifkan akun admin.
     */
    public function toggleActive($id)
    {
        $admin = User::admins()->find($id);

        if (!$admin) {
            return ResponseFormatter::error(null, 'Admin tidak ditemukan', 404);
        }

        // Tidak boleh nonaktifkan diri sendiri
        if ($admin->id === Auth::id()) {
            return ResponseFormatter::error(null, 'Tidak dapat menonaktifkan akun Anda sendiri.', 403);
        }

        // Jika akan menonaktifkan super_admin, cek apakah ini super_admin terakhir yang aktif
        if ($admin->isSuperAdmin() && $admin->isActive()) {
            $activeSuperAdminCount = User::where('role', 'super_admin')
                ->where('is_active', true)
                ->count();

            if ($activeSuperAdminCount <= 1) {
                return ResponseFormatter::error(
                    null,
                    'Tidak dapat menonaktifkan Super Admin terakhir yang aktif.',
                    409
                );
            }
        }

        // Toggle status
        $newStatus = !$admin->is_active;
        $admin->update(['is_active' => $newStatus]);

        // Jika dinonaktifkan: hapus semua token & FCM
        if (!$newStatus) {
            $admin->tokens()->delete();
            $admin->update(['fcm_token' => null]);
        }

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        return ResponseFormatter::success($admin, "Admin berhasil {$statusText}");
    }

    // =========================================================================
    // 5. RESET PASSWORD
    // =========================================================================

    /**
     * POST /api/super-admin/admins/{id}/reset-password
     * Generate password sementara dan update di Firebase + database.
     */
    public function resetPassword($id, \App\Services\FcmService $fcmService)
    {
        $target = User::find($id);

        if (!$target) {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
        }

        // Tidak boleh reset password diri sendiri
        if ($target->id === Auth::id()) {
            return ResponseFormatter::error(null, 'Tidak dapat mereset password Anda sendiri. Gunakan menu profil.', 403);
        }

        // Generate password sementara (12 karakter random)
        $tempPassword = Str::random(12);

        try {
            // 1. Update di Firebase
            if ($target->firebase_uid) {
                $this->firebaseAuth->changeUserPassword($target->firebase_uid, $tempPassword);
            }

            // 2. Update di database
            $target->update([
                'password' => Hash::make($tempPassword),
                'password_reset_by' => Auth::id(),
                'password_reset_at' => now(),
            ]);

            // 3. Hapus semua token agar user dipaksa login ulang
            $target->tokens()->delete();

            // 4. Kirim notifikasi FCM
            if ($target->fcm_token) {
                $fcmService->sendNotification(
                    $target->fcm_token,
                    'Password Direset',
                    'Password Anda telah direset oleh Super Admin. Hubungi klinik untuk mendapatkan password baru.',
                    ['type' => 'password_reset']
                );
            }

            return ResponseFormatter::success(
                ['temporary_password' => $tempPassword],
                'Password berhasil direset. Berikan password sementara ini kepada user.'
            );

        } catch (\Exception $e) {
            Log::error('Reset Password Failed: ' . $e->getMessage());
            return ResponseFormatter::error(
                ['error' => $e->getMessage()],
                'Gagal mereset password. Silakan coba lagi.',
                500
            );
        }
    }
}
