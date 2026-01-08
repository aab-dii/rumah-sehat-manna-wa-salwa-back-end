<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicalRecordController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 10);

        if ($id) {
            $record = MedicalRecord::with(['therapist', 'booking.service'])->find($id);

            if ($record && $record->patient_id == Auth::user()->id) {
                return ResponseFormatter::success(
                    $record,
                    'Data rekam medis berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data rekam medis tidak ada atau tidak berhak',
                    404
                );
            }
        }

        $record = MedicalRecord::with(['therapist', 'booking.service'])
            ->where('patient_id', Auth::user()->id);

        return ResponseFormatter::success(
            $record->orderBy('examination_date', 'desc')->paginate($limit),
            'Data list rekam medis berhasil diambil'
        );
    }
}
