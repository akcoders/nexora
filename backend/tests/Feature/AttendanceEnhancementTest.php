<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearest_assigned_premises_is_used_for_checkin_and_checkout_selfie(): void
    {
        Storage::fake('public');
        $this->seed();
        $user = User::where('email', 'technician@nexora.test')->firstOrFail();
        $far = Premises::create(['name' => 'Far Site', 'latitude' => 19.5, 'longitude' => 73.5, 'radius_meters' => 5, 'active' => true]);
        $near = Premises::create(['name' => 'Near Site', 'latitude' => 19.076, 'longitude' => 72.8777, 'radius_meters' => 5, 'active' => true]);
        $user->premises()->sync([$far->id, $near->id]);
        Sanctum::actingAs($user);

        $this->post('/api/v1/attendance/check-in', [
            'latitude' => 19.076, 'longitude' => 72.8777, 'selfie' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'premises_id' => $near->id]);

        $this->post('/api/v1/attendance/check-out', [
            'latitude' => 19.076, 'longitude' => 72.8777, 'selfie' => UploadedFile::fake()->image('out.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $attendance = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($near->id, $attendance->checkout_premises_id);
        $this->assertNotNull($attendance->checkout_selfie_path);
    }
}
