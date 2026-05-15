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

        // Authorization check: Hanya admin yang bisa melihat daftar user (kecuali jika mencari list terapis)
        if ($request->user()->role !== 'admin') {
            if ($role !== 'terapis') {
                return ResponseFormatter::error(null, 'Hanya admin yang dapat mengakses daftar user', 403);
            }
            // Jika bukan admin tapi mencari role 'terapis', izinkan (untuk dropdown booking)
        }

        $limit = $request->input('limit', 10);
        
        $users = User::query()->select('id', 'name', 'email', 'phone_number', 'role', 'profile_photo_path', 'photo_url', 'specialization', 'deleted_at');

        if ($role) {
            $users->where('role', $role);
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
    public function store(Request $request)
    {
        try {
            // Ensure only admin can create
            if ($request->user()->role !== 'admin') {
                return ResponseFormatter::error(null, 'Unauthorized', 403);
            }

            $request->validate([
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
                'no_hp' => ['required', 'string', 'max:20'],
                'firebase_uid' => ['required', 'string'],
                'role' => ['required', 'string'],
            ]);

            // Langsung masukkan data dan tangkap instancenya, tidak perlu where email lagi
            $user = User::create([
                'name' => $request->nama_lengkap,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->no_hp,
                'gender' => $request->jenis_kelamin ?? 'L',
                'role' => $request->role,
                'firebase_uid' => $request->firebase_uid,
                'job' => $request->pekerjaan,
                'specialization' => $request->specialization,
                'address' => $request->alamat,
                'birth_date' => $request->tgl_lahir,
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

            return ResponseFormatter::success($user, 'User Created by Admin');
        } catch (\Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
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
            if ($request->user()->role !== 'admin') {
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
        // Security check: Hanya admin yang dapat menghapus user
        if (Auth::user()->role !== 'admin') {
            return ResponseFormatter::error(null, 'Hanya admin yang dapat menonaktifkan user', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
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

        $user->delete();

        return ResponseFormatter::success(null, 'User berhasil dinonaktifkan (Soft Delete)');
    }

    /**
     * Restore the specified resource.
     */
    public function restore($id)
    {
        // Security check: Hanya admin yang dapat restore user
        if (Auth::user()->role !== 'admin') {
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