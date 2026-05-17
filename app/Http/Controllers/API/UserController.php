<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $role = $request->input('role');

        // Authorization check: Admin & Super Admin bisa melihat semua, selain itu hanya terapis
        if (!$request->user()->isAdminOrSuperAdmin()) {
            if ($role !== 'terapis') {
                return ResponseFormatter::error(null, 'Hanya admin yang dapat mengakses daftar user', 403);
            }
            // Jika bukan admin tapi mencari role 'terapis', izinkan (untuk dropdown booking)
        }

        $limit = $request->input('limit', 10);
        
        $users = User::query()->select('id', 'name', 'email', 'phone_number', 'role', 'profile_photo_path', 'photo_url', 'specialization', 'deleted_at');

        if ($role) {
            $users->where('role', $role);

            // Sprint 1.4: Sembunyikan terapis tanpa jadwal aktif (Khusus untuk tampilan Pasien)
            if ($role === 'terapis' && $request->user()->role === 'pasien') {
                $users->whereHas('schedules', function ($query) {
                    $query->where('is_active', true);
                });
            }
        }

        // Support fetching soft-deleted users
        if ($request->input('trash') == '1') {
            $users->onlyTrashed();
        }

        $search = $request->input('search');
        if ($search) {
             $users->where('name', 'like', '%' . $search . '%');
        }

        return ResponseFormatter::success(
            $users->paginate($limit),
            'Data list user berhasil diambil'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, \Kreait\Firebase\Contract\Auth $firebaseAuth)
    {
        // 1. Ensure only admin/super_admin can create
        if (!$request->user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Unauthorized', 403);
        }

        $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'no_hp' => ['required', 'string', 'max:20'],
            'role' => ['required', 'string'],
        ]);

        // Sprint 2.1: Proteksi role escalation
        // - Tidak ada yang boleh buat super_admin via endpoint ini
        // - Hanya super_admin yang boleh buat akun admin
        if ($request->role === 'super_admin') {
            return ResponseFormatter::error(null, 'Tidak dapat membuat akun Super Admin melalui endpoint ini.', 403);
        }

        if ($request->role === 'admin' && !$request->user()->isSuperAdmin()) {
            return ResponseFormatter::error(null, 'Hanya Super Admin yang dapat membuat akun admin.', 403);
        }

        $firebaseUser = null;

        try {
            // 2. Buat akun di Firebase Auth
            $firebaseUser = $firebaseAuth->createUser([
                'email'       => $request->email,
                'password'    => $request->password,
                'displayName' => $request->nama_lengkap,
            ]);

            // 3. Simpan ke database Laravel dengan UID Firebase yang didapat
            $user = User::create([
                'name' => $request->nama_lengkap,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->no_hp,
                'gender' => $request->jenis_kelamin ?? 'L',
                'role' => $request->role,
                'firebase_uid' => $firebaseUser->uid,
                'job' => $request->pekerjaan,
                'specialization' => $request->specialization,
                'address' => $request->alamat,
                'birth_date' => $request->tgl_lahir,
                'is_active' => true,
            ]);

            // Auto-create Schedule if role is 'terapis'
            if ($request->role === 'terapis') {
                $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                foreach ($days as $day) {
                    Schedule::create([
                        'therapist_id' => $user->id,
                        'day' => $day,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'is_active' => false,
                        'type' => 'regular',
                        'location_type' => 'clinic',
                    ]);
                }
            }

            return ResponseFormatter::success($user, 'User Created successfully with Firebase integration');
        } catch (\Exception $error) {
            // Rollback Firebase jika database Laravel gagal menyimpan data
            if ($firebaseUser) {
                try {
                    $firebaseAuth->deleteUser($firebaseUser->uid);
                } catch (\Exception $rollbackError) {
                    \Illuminate\Support\Facades\Log::error('Firebase Rollback Failed in UserController: ' . $rollbackError->getMessage());
                }
            }

            return ResponseFormatter::error([
                'message' => 'Something went wrong during creation',
                'error' => $error->getMessage(),
            ], 'Creation Failed', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $authUser = Auth::user();
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
        }

        // Security check: Pasien hanya boleh melihat dirinya sendiri
        if ($authUser->role === 'pasien' && $authUser->id != $user->id) {
            return ResponseFormatter::error(null, 'Akses ditolak', 403);
        }

        // birth_date dikembalikan sebagai string YYYY-MM-DD
        return ResponseFormatter::success($user, 'Data user berhasil diambil');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            if (!$request->user()->isAdminOrSuperAdmin()) {
                return ResponseFormatter::error(null, 'Unauthorized', 403);
            }

            $user = User::find($id);
            if (!$user) {
                return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
            }

            $request->validate([
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'no_hp' => ['required', 'string', 'max:20'],
                'alamat' => ['required', 'string'],
            ]);

            $user->name = $request->nama_lengkap;
            $user->phone_number = $request->no_hp;
            $user->address = $request->alamat;

            // Optional fields
            if ($request->has('pekerjaan')) {
                $user->job = $request->pekerjaan;
            }
            if ($request->has('specialization')) {
                $user->specialization = $request->specialization;
            }
            if ($request->has('tgl_lahir')) {
                $user->birth_date = $request->tgl_lahir;
            }
            if ($request->has('jenis_kelamin')) {
                $user->gender = $request->jenis_kelamin;
            }

            // Handle Profile Photo
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('profile-photos', 'public');
                $user->profile_photo_path = $path;
            }

            $user->save();

            return ResponseFormatter::success($user, 'Data user berhasil diperbarui');
        } catch (\Exception $error) {
             return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error->getMessage(),
            ], 'Update Failed', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Security check: Hanya admin/super_admin yang dapat menghapus user
        if (!Auth::user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Hanya admin yang dapat menonaktifkan user', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
        }

        // Sprint 2.1: Tidak boleh hapus diri sendiri
        if ($user->id === Auth::id()) {
            return ResponseFormatter::error(null, 'Tidak dapat menghapus akun Anda sendiri.', 403);
        }

        // Sprint 2.1: Tidak boleh hapus super_admin terakhir
        if ($user->isSuperAdmin()) {
            $activeSuperAdminCount = User::where('role', 'super_admin')->whereNull('deleted_at')->count();
            if ($activeSuperAdminCount <= 1) {
                return ResponseFormatter::error(null, 'Tidak dapat menghapus Super Admin terakhir.', 409);
            }
        }

        // Validasi: Jika terapis, cek apakah ada booking aktif (pending/confirmed)
        if ($user->role === 'terapis') {
            $activeBookings = Booking::where('therapist_id', $id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($activeBookings) {
                return ResponseFormatter::error(
                    null,
                    'Data tidak dapat dihapus karena masih memiliki janji temu aktif.',
                    400
                );
            }
        }

        // Sprint 2.1: Hapus token & FCM sebelum soft delete
        $user->tokens()->delete();
        $user->update(['fcm_token' => null]);

        $user->delete();

        return ResponseFormatter::success(null, 'User berhasil dinonaktifkan (Soft Delete)');
    }

    /**
     * Restore the specified resource.
     */
    public function restore($id)
    {
        // Security check: Hanya admin/super_admin yang dapat restore user
        if (!Auth::user()->isAdminOrSuperAdmin()) {
            return ResponseFormatter::error(null, 'Hanya admin yang dapat mengaktifkan kembali user', 403);
        }

        $user = User::onlyTrashed()->find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan di sampah', 404);
        }

        $user->restore();

        return ResponseFormatter::success($user, 'User berhasil diaktifkan kembali');
    }
}