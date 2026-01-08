<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Bekam (Hijamah)',
                'description' => 'Terapi pengobatan tradisional dengan cara membuang darah kotor (toksin) dari dalam tubuh melalui permukaan kulit. Bermanfaat untuk melancarkan peredaran darah dan membuang racun.',
                'price' => 100000,
                'duration_minutes' => 45,
                'image_url' => null,
            ],
            [
                'name' => 'Akupuntur',
                'description' => 'Teknik pengobatan tradisional dengan menusukkan jarum tipis ke titik-titik tertentu pada tubuh untuk menyeimbangkan energi (Qi), mengurangi nyeri, dan meningkatkan kesehatan.',
                'price' => 150000,
                'duration_minutes' => 60,
                'image_url' => null,
            ],
            [
                'name' => 'Pijat Refleksi',
                'description' => 'Pijat yang berfokus pada penekanan titik-titik refleksi di kaki dan tangan yang terhubung dengan organ tubuh lainnya. Cocok untuk relaksasi dan melancarkan sirkulasi darah.',
                'price' => 80000,
                'duration_minutes' => 60,
                'image_url' => null,
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
