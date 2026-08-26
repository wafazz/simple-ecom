<?php

namespace Tests\Feature;

use App\Models\IntegrationToken;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-006 — Planning §11.B.3. The only table holding credentials. */
class IntegrationTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tokens_are_encrypted_at_rest(): void
    {
        // A plaintext bearer token in a nightly mysqldump is a credential leak
        // waiting for a mislaid backup.
        $token = IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'plaintext-access-token',
            'refresh_token' => 'plaintext-refresh-token',
            'expires_at' => now()->addHours(10),
            'connected_at' => now(),
        ]);

        $raw = DB::table('integration_tokens')->where('id', $token->id)->first();

        $this->assertNotSame('plaintext-access-token', $raw->access_token);
        $this->assertStringNotContainsString('plaintext', $raw->access_token);
        $this->assertStringNotContainsString('plaintext', $raw->refresh_token);

        $this->assertSame('plaintext-access-token', $token->fresh()->access_token);
        $this->assertSame('plaintext-refresh-token', $token->fresh()->refresh_token);
    }

    #[Test]
    public function the_app_cipher_is_authenticated(): void
    {
        // GCM, not CBC: a tampered row must fail the tag check rather than
        // decrypt to attacker-influenced bytes that get sent as a header.
        $this->assertSame('AES-256-GCM', config('app.cipher'));
    }

    #[Test]
    public function tokens_are_hidden_from_array_and_json_output(): void
    {
        $token = IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
        ]);

        $this->assertArrayNotHasKey('access_token', $token->toArray());
        $this->assertArrayNotHasKey('refresh_token', $token->toArray());
        $this->assertStringNotContainsString('secret-access', $token->toJson());
    }

    #[Test]
    public function only_one_token_row_can_exist_per_provider(): void
    {
        IntegrationToken::create(['provider' => 'easyparcel']);

        $this->expectException(QueryException::class);

        IntegrationToken::create(['provider' => 'easyparcel']);
    }

    #[Test]
    public function a_token_with_no_expiry_counts_as_expired(): void
    {
        $this->assertTrue(IntegrationToken::create(['provider' => 'easyparcel'])->isExpired());

        $live = IntegrationToken::create([
            'provider' => 'toyyibpay',
            'expires_at' => now()->addHour(),
        ]);
        $this->assertFalse($live->isExpired());
    }
}
