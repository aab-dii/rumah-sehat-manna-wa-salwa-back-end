<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 3);
        $name = $request->input('name');

        if ($id) {
            $service = Service::find($id);

            if ($service) {
                return ResponseFormatter::success(
                    $service,
                    'Data layanan berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data layanan tidak ada',
                    404
                );
            }
        }

        $service = Service::query();

        if ($name) {
            $service->where('name', 'like', '%' . $name . '%');
        }

        return ResponseFormatter::success(
            $service->paginate($limit),
            'Data list layanan berhasil diambil'
        );
    }

    public function show($id)
    {
        $service = Service::find($id);

        if (!$service) {
            return ResponseFormatter::error(
                null,
                'Data layanan tidak ada',
                404
            );
        }

        return ResponseFormatter::success(
            $service,
            'Data layanan berhasil diambil'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'duration_minutes' => 'required|integer',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $data['image_url'] = $path;
        }

        $service = Service::create($data);

        return ResponseFormatter::success(
            $service,
            'Layanan berhasil ditambahkan'
        );
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'duration_minutes' => 'sometimes|integer',
            'description' => 'sometimes|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $service = Service::find($id);

        if (!$service) {
            return ResponseFormatter::error(
                null,
                'Data layanan tidak ada',
                404
            );
        }

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $data['image_url'] = $path;
        }

        $service->update($data);

        return ResponseFormatter::success(
            $service,
            'Layanan berhasil diupdate'
        );
    }

    public function destroy($id)
    {
        $service = Service::find($id);

        if (!$service) {
            return ResponseFormatter::error(
                null,
                'Data layanan tidak ada',
                404
            );
        }

        $service->delete(); // Soft delete

        return ResponseFormatter::success(
            null,
            'Layanan berhasil dihapus'
        );
    }
}
