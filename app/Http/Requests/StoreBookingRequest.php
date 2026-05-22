<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ═══════════════════════════════════════════════════════════════════════
 * FORM REQUEST: StoreBookingRequest
 * ═══════════════════════════════════════════════════════════════════════
 *
 * KEAMANAN PARAMETER TAMPERING:
 * Request ini HANYA menerima field yang boleh dikirim oleh client Android.
 * Field sensitif seperti 'price', 'total_price', 'status', 'payment_status'
 * TIDAK PERNAH diterima dari client — nilainya selalu diambil dari database
 * di BookingController::store().
 *
 * Ini mencegah skenario di mana user nakal menggunakan tools seperti
 * BurpSuite atau Charles Proxy untuk mengubah nominal harga menjadi lebih
 * murah, atau langsung mem-bypass alur pembayaran dengan mengirim
 * status: 'confirmed' atau payment_status: 'paid'.
 *
 * @see \App\Http\Controllers\API\BookingController::store()
 * ═══════════════════════════════════════════════════════════════════════
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otentikasi sudah dihandle oleh middleware 'auth:sanctum'.
        // Otorisasi role (pasien vs admin) dihandle di controller.
        return true;
    }

    /**
     * Aturan validasi yang ketat.
     *
     * PENTING: Hanya field-field ini yang diterima dari client.
     * Field 'price', 'total_price', 'status', dan 'payment_status'
     * SENGAJA TIDAK ADA di sini — nilainya ditentukan oleh server.
     */
    public function rules(): array
    {
        return [
            // ── Field Wajib (Dikirim oleh Android) ──────────────────────
            'therapist_id'      => 'required|exists:users,id',       // ID terapis yang dipilih
            'service_id'        => 'required|exists:services,id',    // ID layanan → harga diambil dari DB
            'booking_date'      => 'required|date|after_or_equal:today', // Tanggal booking
            'booking_time'      => 'required|date_format:H:i',      // Jam booking (format 24 jam)

            // ── Field Opsional ──────────────────────────────────────────
            'patient_id'        => 'nullable|exists:users,id',       // Hanya dikirim oleh admin
            'location_type'     => 'nullable|in:clinic,home',        // Lokasi terapi
            'address'           => 'nullable|string|max:500',        // Alamat jika home visit
            'notes'             => 'nullable|string|max:1000',       // Catatan tambahan
            'payment_method'    => 'nullable|in:cash,transfer',      // Metode pembayaran
            'proof_of_transfer' => 'nullable|image|max:2048',       // Bukti transfer (maks 2MB)

            // ╔═══════════════════════════════════════════════════════════╗
            // ║ FIELD YANG SENGAJA TIDAK ADA (DITOLAK DARI CLIENT):     ║
            // ║                                                         ║
            // ║ ❌ 'price'          → Diambil dari tabel services       ║
            // ║ ❌ 'total_price'    → Dihitung di server (price + fee)  ║
            // ║ ❌ 'status'         → Ditentukan oleh business logic    ║
            // ║ ❌ 'payment_status' → Ditentukan oleh business logic    ║
            // ║ ❌ 'admin_fee'      → Diambil dari config('clinic')     ║
            // ╚═══════════════════════════════════════════════════════════╝
        ];
    }

    /**
     * Pesan error kustom dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'therapist_id.required'   => 'Terapis harus dipilih.',
            'therapist_id.exists'     => 'Terapis tidak ditemukan dalam sistem.',
            'service_id.required'     => 'Layanan harus dipilih.',
            'service_id.exists'       => 'Layanan tidak ditemukan dalam sistem.',
            'booking_date.required'   => 'Tanggal booking harus diisi.',
            'booking_date.date'       => 'Format tanggal tidak valid.',
            'booking_date.after_or_equal' => 'Tidak dapat memesan untuk tanggal yang sudah lewat.',
            'booking_time.required'   => 'Waktu booking harus diisi.',
            'booking_time.date_format'=> 'Format waktu harus HH:MM (contoh: 10:00).',
            'payment_method.in'       => 'Metode pembayaran harus cash atau transfer.',
            'proof_of_transfer.image' => 'Bukti transfer harus berupa file gambar.',
            'proof_of_transfer.max'   => 'Ukuran bukti transfer maksimal 2MB.',
        ];
    }
}
