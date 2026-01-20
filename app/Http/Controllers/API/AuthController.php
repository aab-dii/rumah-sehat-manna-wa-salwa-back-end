<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
                'no_hp' => ['required', 'string', 'max:20'],
                'firebase_uid' => ['required', 'string'],
            ]);

            User::create([
                'name' => $request->nama_lengkap,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->no_hp,
                'gender' => $request->jenis_kelamin ?? 'L', 
                'role' => 'pasien',
                'firebase_uid' => $request->firebase_uid,
                'job' => $request->pekerjaan,
                'address' => $request->alamat,
                'birth_date' => $request->tgl_lahir,
            ]);

            $user = User::where('email', $request->email)->first();
            $tokenResult = $user->createToken('authToken')->plainTextToken;

            $formattedUser = [
                'firebase_uid' => $user->firebase_uid,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'no_hp' => $user->phone_number,
                'pekerjaan' => $user->job,
                'tgl_lahir' => $user->birth_date,
                'alamat' => $user->address,
                'jenis_kelamin' => $user->gender,
                'role' => $user->role,
                'access_token' => $tokenResult
            ];

            return ResponseFormatter::success($formattedUser, 'User Registered');
        } catch (\Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error->getMessage(),
            ], 'Authentication Failed', 500);
        }
    }

    public function createUserByAdmin(Request $request)
    {
        try {
            // Validation can be same or stricter
            $request->validate([
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
                'no_hp' => ['required', 'string', 'max:20'],
                'firebase_uid' => ['required', 'string'],
                'role' => ['required', 'string'], // Admin must specify role
            ]);

            // Ensure only admin can call this (Double check, though middleware should handle auth)
            if ($request->user()->role !== 'admin') {
                return ResponseFormatter::error(null, 'Unauthorized', 403);
            }

            User::create([
                'name' => $request->nama_lengkap,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->no_hp,
                'gender' => $request->jenis_kelamin ?? 'L',
                'role' => $request->role,
                'firebase_uid' => $request->firebase_uid,
                'job' => $request->pekerjaan, // Nullable
                'specialization' => $request->specialization, // Nullable, Array
                'address' => $request->alamat,
                'birth_date' => $request->tgl_lahir,
            ]);

            $user = User::where('email', $request->email)->first();
            // No need to create token for the new user since Admin is creating it

            // Auto-create Schedule if role is 'terapis'
            if ($request->role === 'terapis') {
                $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                foreach ($days as $day) {
                    \App\Models\Schedule::create([
                        'therapist_id' => $user->id,
                        'day' => $day,
                        'start_time' => '09:00', // Default start
                        'end_time' => '17:00',   // Default end
                        'is_active' => false,    // Default inactive per user request
                        'type' => 'regular',
                        'location_type' => 'clinic', // Default
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

    public function getUserByFirebaseUid($uid)
    {
        $user = User::where('firebase_uid', $uid)->first();
        
        if ($user) {
            $tokenResult = $user->createToken('authToken')->plainTextToken;

            $formattedUser = [
                'firebase_uid' => $user->firebase_uid,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'no_hp' => $user->phone_number,
                'pekerjaan' => $user->job,
                'tgl_lahir' => $user->birth_date,
                'alamat' => $user->address,
                'jenis_kelamin' => $user->gender,
                'role' => $user->role,
                'access_token' => $tokenResult
            ];

            return ResponseFormatter::success($formattedUser, 'Data user berhasil diambil');
        } else {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'email|required',
                'password' => 'required'
            ]);

            $credentials = request(['email', 'password']);
            if (!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized'
                ], 'Authentication Failed', 401);
            }

            $user = User::where('email', $request->email)->first();
            if (!Hash::check($request->password, $user->password, [])) {
                throw new \Exception('Invalid Credentials');
            }

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Authenticated');
        } catch (\Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error->getMessage(),
            ], 'Authentication Failed', 500);
        }
    }

    public function fetch(Request $request)
    {
        return ResponseFormatter::success($request->user(), 'Data profile user berhasil diambil');
    }

    public function all(Request $request)
    {
        // Allow admin to access all, or anyone to fetch 'terapis' list
        $role = $request->input('role');
        
        if ($request->user()->role !== 'admin' && $role !== 'terapis') {
            return ResponseFormatter::error(null, 'Unauthorized', 403);
        }

        $limit = $request->input('limit', 10);
        $role = $request->input('role');

        $users = User::query();

        if ($role) {
            $users->where('role', $role);
        }

        // Support fetching soft-deleted users
        if ($request->input('trash') == '1') { // or 'true'
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

    public function updateProfile(Request $request)
    {
        $data = $request->all();

        $user = Auth::user();
        
        // Android app sends 'nama_lengkap' but model expects 'name'
        if (isset($data['nama_lengkap'])) {
            $user->name = $data['nama_lengkap'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (isset($data['no_hp'])) {
            $user->phone_number = $data['no_hp'];
        }
        if (isset($data['pekerjaan'])) {
            $user->job = $data['pekerjaan'];
        }
        if (isset($data['alamat'])) {
            $user->address = $data['alamat'];
        }
        if (isset($data['tgl_lahir'])) {
            $user->birth_date = $data['tgl_lahir'];
        }
        if (isset($data['jenis_kelamin'])) {
            $user->gender = $data['jenis_kelamin'];
        }

        $user->save();

        return ResponseFormatter::success($user, 'Profile Updated');
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken()->delete();
        return ResponseFormatter::success($token, 'Token Revoked');
    }

    // Delete user (Soft Delete)
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
        }

        $user->delete();

        return ResponseFormatter::success(null, 'User berhasil dinonaktifkan (Soft Delete)');
    }

    // Restore user
    // Admin update user
    public function updateUserByAdmin(Request $request, $id)
    {
        try {
            if ($request->user()->role !== 'admin') {
                return ResponseFormatter::error(null, 'Unauthorized', 403);
            }

            $user = User::find($id);
            if (!$user) {
                return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
            }

            $data = $request->validate([
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

    public function restore($id)
    {
        $user = User::onlyTrashed()->find($id);

        if (!$user) {
            return ResponseFormatter::error(null, 'User tidak ditemukan di sampah', 404);
        }

        $user->restore();

        return ResponseFormatter::success($user, 'User berhasil diaktifkan kembali');
    }
}
