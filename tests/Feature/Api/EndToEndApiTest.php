<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Prodi;
use App\Models\Pendaftar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->artisan('db:seed --class=WhiteTestingSeeder');
    }

    public function test_superadmin_flow(): void
    {
        $user = User::where('role', 'superadmin')->first();
        $this->actingAs($user);

        $response = $this->getJson('/api/superadmin/dashboard');
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data' => ['stats', 'recent_audits']]);

        $response = $this->getJson('/api/superadmin/prodi');
        $response->assertStatus(200);
    }

    public function test_admin_flow(): void
    {
        $user = User::where('role', 'admin')->first();
        $this->actingAs($user);

        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data' => ['stats', 'recent_pendaftar']]);
    }

    public function test_akademik_flow(): void
    {
        $user = User::where('role', 'akademik')->first();
        $this->actingAs($user);

        $response = $this->getJson('/api/akademik/dashboard');
        $response->assertStatus(200);

        $response = $this->getJson('/api/akademik/antrean');
        $response->assertStatus(200);
    }

    public function test_kaprodi_flow(): void
    {
        $user = User::where('role', 'kaprodi')->first();
        $this->actingAs($user);

        $response = $this->getJson('/api/kaprodi/dashboard');
        $response->assertStatus(200);
    }
}
