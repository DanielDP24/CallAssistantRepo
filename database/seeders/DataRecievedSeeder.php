<?php

namespace Database\Seeders;

use App\Models\DataRecieved;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DataRecievedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run()
    {
        DataRecieved::create([
            'uuid' => Str::uuid(),
            'name' => 'Carlos',
            'email' => 'carlos@example.com',
            'company' => 'MiEmpresa',
            'callerNum' => '+34123456789'
        ]);
    }
}
