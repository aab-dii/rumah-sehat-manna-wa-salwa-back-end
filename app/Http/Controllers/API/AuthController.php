<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    public function syncFirebase(Request $request)
    {
        try {
            $request->validate([
                'id_token' => 'required'
            ]);

            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($request->id_token);
            $uid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $uid)->first();
            if (!$user) {
                return ResponseFormatter::error(null, 'Akun tidak terdaftar di sistem kami.', 404);
            } 

            $user->tokens()->delete();
            $tokenResult = $user->createToken('authToken')->plainTextToken;

            $formattedUser = [
                'id' => $user->id,
                'firebase_uid' => $user->firebase_uid,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'no_hp' => $user->phone_number,
                'pekerjaan' => $user->job,
                'tgl_lahir' => $user->birth_date, // String YYYY-MM-DD (bukan Carbon), aman dari timezone shift
                'alamat' => $user->address,
                'jenis_kelamin' => $user->gender,
                'role' => $user->role,
                'foto_url' => $user->photo_url,
                'profile_photo_path' => $user->profile_photo_path,
                'access_token' => $tokenResult
            ];

            return ResponseFormatter::success($formattedUser, 'Sesi sinkronisasi berhasil.');

        } catch (\Exception $e) {
            Log::error('Firebase Sync Error: ' . $e->getMessage());
            return ResponseFormatter::error(
                ['error' => $e->getMessage()],
                'Sesi Anda telah berakhir, silakan masuk kembali.',
                401
            );
        }
    }
    
    public function register(Request $request)
    {
        try {
            $request->validate([
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'max:64'],
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
                'photo_url' => $request->foto_url, // Simpan foto_url (khususnya untuk Google)
            ]);

            $user = User::where('email', $request->email)->first();
            $tokenResult = $user->createToken('authToken')->plainTextToken;

            $formattedUser = [
                'id' => $user->id,
                'firebase_uid' => $user->firebase_uid,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'no_hp' => $user->phone_number,
                'pekerjaan' => $user->job,
                'tgl_lahir' => $user->birth_date, // String YYYY-MM-DD (bukan Carbon), aman dari timezone shift
                'alamat' => $user->address,
                'jenis_kelamin' => $user->gender,
                'role' => $user->role,
                'foto_url' => $user->photo_url,
                'profile_photo_path' => $user->profile_photo_path,
                'access_token' => $tokenResult
            ];

            return ResponseFormatter::success($formattedUser, 'Registrasi akun baru berhasil.');
        } catch (\Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Layanan sedang bermasalah',
                'error' => $error->getMessage(),
            ], 'Registrasi gagal, silakan coba lagi nanti.', 500);
        }
    }



    public function getUserByFirebaseUid($uid)
    {
        $user = User::where('firebase_uid', $uid)->first();
        
        if ($user) {
            // ── Revoke semua token lama sebelum buat token baru ───────────
            // Tanpa ini, setiap kali app dibuka akan menumpuk token baru
            // di tabel personal_access_tokens sehingga database membengkak.
            $user->tokens()->delete();

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            $formattedUser = [
                'id' => $user->id,
                'firebase_uid' => $user->firebase_uid,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'no_hp' => $user->phone_number,
                'pekerjaan' => $user->job,
                'tgl_lahir' => $user->birth_date, 
                'alamat' => $user->address,
                'jenis_kelamin' => $user->gender,
                'role' => $user->role,
                'foto_url' => $user->photo_url,
                'profile_photo_path' => $user->profile_photo_path,
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
                'password' => 'required|max:64'
            ]);

            $credentials = request(['email', 'password']);
            if (!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Invalid Credentials'
                ], 'Email atau password salah.', 401);
            }

            $user = User::where('email', $request->email)->first();
            if (!Hash::check($request->password, $user->password, [])) {
                return ResponseFormatter::error([
                    'message' => 'Invalid Credentials'
                ], 'Email atau password salah.', 401);
            }

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Selamat Datang! Login berhasil.');
        } catch (\Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Layanan sedang bermasalah',
                'error' => $error->getMessage(),
            ], 'Terjadi kesalahan sistem, silakan coba lagi nanti.', 500);
        }
    }

    public function fetch(Request $request)
    {
        return ResponseFormatter::success($request->user(), 'Data profile user berhasil diambil');
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
        if (isset($data['foto_url'])) {
            $user->photo_url = $data['foto_url'];
        }

        $user->save();

        return ResponseFormatter::success($user, 'Profil Anda berhasil diperbarui.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->update(['fcm_token' => null]);
            $accessToken = $user->currentAccessToken();
            $token = $accessToken ? $accessToken->delete() : null;
        } else {
            $token = null;
        }
        return ResponseFormatter::success($token, 'Berhasil keluar dari aplikasi.');
    }

    // Delete user (Soft Delete)

    public function updateFcmToken(Request $request) {
        $request->validate(['fcm_token' => 'required']);
        $user = Auth::user();
        $user->update(['fcm_token' => $request->fcm_token]);
        return ResponseFormatter::success(['fcm_token' => $user->fcm_token], 'Token berhasil diperbarui');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ResponseFormatter::error(null, 'Email tidak terdaftar di sistem kami.', 404);
        }

        if ($user->role !== 'pasien') {
            return ResponseFormatter::error(null, 'Akun staf (Terapis/Admin) tidak dapat direset mandiri. Silakan hubungi Super Admin klinik.', 403);
        }

        try {
            $firebaseUser = $this->firebaseAuth->getUserByEmail($request->email);
            
            // Periksa metode login (provider)
            $isGoogle = false;
            foreach ($firebaseUser->providerData as $provider) {
                if ($provider->providerId === 'google.com') {
                    $isGoogle = true;
                    break;
                }
            }

            if ($isGoogle) {
                return ResponseFormatter::error(
                    null, 
                    "Akun ini terdaftar menggunakan Google.\nSilakan gunakan tombol 'Lanjutkan dengan Google' untuk masuk ke aplikasi.", 
                    403
                );
            }
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return ResponseFormatter::error(null, 'Email tidak terdaftar di sistem kami.', 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Forgot Password Firebase Error: ' . $e->getMessage());
            return ResponseFormatter::error(null, 'Gagal memvalidasi status akun.', 500);
        }

        return ResponseFormatter::success(null, 'Validasi berhasil, email reset siap dikirim.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8|max:64'
        ]);

        $user = Auth::user();

        if (!Hash::check($request->old_password, $user->password)) {
            return ResponseFormatter::error(null, 'Password lama tidak sesuai.', 422);
        }

        try {
            if ($user->firebase_uid) {
                $this->firebaseAuth->changeUserPassword($user->firebase_uid, $request->new_password);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            $user->tokens()->delete();

            return ResponseFormatter::success(null, 'Password berhasil diubah. Silakan login kembali.');
        } catch (\Kreait\Firebase\Exception\Auth\WeakPassword $e) {
            return ResponseFormatter::error(null, 'Password terlalu lemah, minimal 8 karakter dengan kombinasi huruf dan angka.', 422);
        } catch (\Exception $e) {
            Log::error('Change Password Error: ' . $e->getMessage());
            return ResponseFormatter::error(
                ['error' => $e->getMessage()],
                'Gagal mengubah password. Silakan coba lagi nanti.',
                500
            );
        }
    }
}
