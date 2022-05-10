<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $cities = ['Алматы', 'Астана', 'Бишкек'];
        foreach ($cities as $city) {
            $new = new City();
            $new->title = $city;
            $new->saveQuietly();
        }
    }
}
