<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class QmsDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(\App\Services\QmsDevelopmentData::class)->run(true);
        $this->command?->info('QMS development data seeded; Admin/ATL reused unchanged. New credentials and the report are in private storage.');
    }
}
