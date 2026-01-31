<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $countries = [
            [
                'name' => 'United States',
                'code' => 'US',
                'currency' => 'USD',
                'phone_code' => '+1',
            ],
            [
                'name' => 'Canada',
                'code' => 'CA',
                'currency' => 'CAD',
                'phone_code' => '+1',
            ],
            [
                'name' => 'France',
                'code' => 'FR',
                'currency' => 'EUR',
                'phone_code' => '+33',
            ],
             [
                'name' => 'Senegal',
                'code' => 'SN',
                'currency' => 'XOF',
                'phone_code' => '+221',
            ],
             [
                'name' => 'Ivory Coast',
                'code' => 'CI',
                'currency' => 'XOF',
                'phone_code' => '+225',
            ],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(
                ['code' => $country['code']],
                $country
            );
        }
    }
}
