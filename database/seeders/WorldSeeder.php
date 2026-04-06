<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\LocationConnection;
use Illuminate\Database\Seeder;

class WorldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now();

        $locations = [
            [
                'slug' => 'yongan',
                'name' => 'Yongan',
                'region' => 'Shinsoo',
                'description' => 'A fortified market town with a stable, smithy and safe message board.',
                'threat_level' => 1,
                'is_safe_zone' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'slug' => 'joan',
                'name' => 'Joan',
                'region' => 'Chunjo',
                'description' => 'A scholarly border town where recruits prepare before leaving the walls.',
                'threat_level' => 1,
                'is_safe_zone' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'slug' => 'pyungmoo',
                'name' => 'Pyungmoo',
                'region' => 'Jinno',
                'description' => 'A hard military camp where veterans trade rumors and sharpen steel.',
                'threat_level' => 1,
                'is_safe_zone' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'slug' => 'seungryong-valley',
                'name' => 'Seungryong Valley',
                'region' => 'Borderlands',
                'description' => 'The second frontier map, where corrupted beasts give way to stronger orc warbands and harsher rewards.',
                'threat_level' => 5,
                'is_safe_zone' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'slug' => 'monkey-dungeon',
                'name' => 'Monkey Dungeon',
                'region' => 'Dungeon',
                'description' => 'The first true dungeon, packed with ape packs, tighter corridors and the first real boss pressure wall.',
                'threat_level' => 6,
                'is_safe_zone' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        Location::query()->upsert(
            $locations,
            ['slug'],
            ['name', 'region', 'description', 'threat_level', 'is_safe_zone', 'updated_at'],
        );

        $locationIds = Location::query()
            ->pluck('id', 'slug');

        $connections = [
            ['from_location_id' => $locationIds['yongan'], 'to_location_id' => $locationIds['seungryong-valley'], 'travel_seconds' => 55, 'stamina_cost' => 3, 'required_level' => 20],
            ['from_location_id' => $locationIds['seungryong-valley'], 'to_location_id' => $locationIds['yongan'], 'travel_seconds' => 50, 'stamina_cost' => 2, 'required_level' => 1],
            ['from_location_id' => $locationIds['seungryong-valley'], 'to_location_id' => $locationIds['monkey-dungeon'], 'travel_seconds' => 65, 'stamina_cost' => 4, 'required_level' => 25],
            ['from_location_id' => $locationIds['monkey-dungeon'], 'to_location_id' => $locationIds['seungryong-valley'], 'travel_seconds' => 60, 'stamina_cost' => 3, 'required_level' => 1],
        ];

        $connections = array_map(function (array $connection) use ($timestamp): array {
            return [
                ...$connection,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $connections);

        LocationConnection::query()->upsert(
            $connections,
            ['from_location_id', 'to_location_id'],
            ['travel_seconds', 'stamina_cost', 'required_level', 'updated_at'],
        );

        $this->call(GameContentSeeder::class);
    }
}
