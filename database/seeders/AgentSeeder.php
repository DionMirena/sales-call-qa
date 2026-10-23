<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            ['name' => 'Sarah Mitchell',  'team' => 'Team A'],
            ['name' => 'James O\'Brien',  'team' => 'Team A'],
            ['name' => 'Emma Clarke',     'team' => 'Team B'],
            ['name' => 'Daniel Hughes',   'team' => 'Team B'],
            ['name' => 'Priya Sharma',    'team' => 'Team C'],
        ];

        foreach ($agents as $agent) {
            Agent::firstOrCreate(['name' => $agent['name']], $agent);
        }
    }
}
