<?php

namespace Database\Seeders;
use App\Models\DataGiven;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DataGivenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DataGiven::create([
            'uuid' => Str::uuid(),
            'name' => 'Carlos',
            'email' => 'carlos@example.com',
            'company' => 'MiEmpresa',
            'callerNum' => '+34123456789'
        ]);
    }
}
