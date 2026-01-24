<?php


namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for user profile customization endpoints.
 */
class UserProfileCustomizationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure non-admin users cannot upload GIF avatars.
     *
     * @return void
     */
    public function test_non_admin_cannot_upload_gif_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $gif = UploadedFile::fake()->create('avatar.gif', 50, 'image/gif');

        $this->actingAs($user, 'sanctum')
            ->post('/api/user/profile', [
                'avatar' => $gif,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /**
     * Ensure admin users can upload GIF avatars.
     *
     * @return void
     */
    public function test_admin_can_upload_gif_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $gif = UploadedFile::fake()->create('avatar.gif', 50, 'image/gif');

        $payload = $this->actingAs($user, 'sanctum')
            ->post('/api/user/profile', [
                'avatar' => $gif,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->json('user');

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['avatar_url'] ?? null);
        $this->assertStringContainsString('/storage/users/' . $user->id . '/', (string)($payload['avatar_url'] ?? ''));
    }

    /**
     * Ensure a user can update their avatar frame color.
     *
     * @return void
     */
    public function test_user_can_update_avatar_frame_color(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $payload = $this->actingAs($user, 'sanctum')
            ->post('/api/user/profile', [
                'avatar_frame_color' => '#ff00ff',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->json('user');

        $this->assertSame('#ff00ff', $payload['avatar_frame_color'] ?? null);
    }
}
