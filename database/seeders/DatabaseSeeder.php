<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $firstUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $otherUsers = User::factory(8)->create();

        foreach ($otherUsers->take(5) as $contactUser) {
            Contact::firstOrCreate(
                [
                    'sender_id' => $firstUser->id,
                    'receiver_id' => $contactUser->id,
                ],
                [
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]
            );
        }
    }
}
