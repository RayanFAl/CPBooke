<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_is_not_publicly_readable_by_guessed_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->post('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'medium',
            'subject' => 'Private file',
            'message' => 'Secret attachment',
            'attachment' => UploadedFile::fake()->create('secret.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = SupportMessage::query()->firstOrFail();

        Storage::disk('local')->assertExists($message->attachment_path);
        Storage::disk('public')->assertMissing($message->attachment_path);

        $response = $this->get('/storage/'.$message->attachment_path);
        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            'Expected public storage path to be inaccessible, got '.$response->status()
        );
    }

    public function test_signed_url_downloads_attachment_and_unsigned_url_is_rejected(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->post('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'medium',
            'subject' => 'Signed download',
            'message' => 'Please download',
            'attachment' => UploadedFile::fake()->create('boarding.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = SupportMessage::query()->firstOrFail();
        $signedUrl = app(SupportAttachmentStorage::class)->temporaryUrl($message);

        $this->assertNotNull($signedUrl);

        $this->get($signedUrl)->assertOk();

        $this->get(route('support.attachments.download', $message))
            ->assertForbidden();
    }

    public function test_authenticated_non_owner_cannot_use_signed_url(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $intruder = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($owner);

        $this->post('/api/v1/support/tickets/messages', [
            'category' => 'document_request',
            'priority' => 'medium',
            'subject' => 'Owner only',
            'message' => 'Private',
            'attachment' => UploadedFile::fake()->image('passport.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = SupportMessage::query()->firstOrFail();
        $signedUrl = app(SupportAttachmentStorage::class)->temporaryUrl($message);

        $this->actingAs($intruder)
            ->get($signedUrl)
            ->assertForbidden();
    }

    public function test_support_agent_can_download_via_signed_url(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);
        $agent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $agent->syncRolesByName([RbacRegistry::ROLE_SUPPORT_AGENT]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-SEC-0001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $agent->id,
            'subject' => 'Agent download',
            'description' => 'Agent should access attachment.',
        ]);

        $path = 'support/attachments/agent-file.pdf';
        Storage::disk('local')->put($path, 'agent-file-bytes');

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Attached',
            'attachment_path' => $path,
            'attachment_name' => 'agent-file.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 15,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'support.attachments.download',
            now()->addMinutes(10),
            ['message' => $message->id],
        );

        $this->actingAs($agent)
            ->get($signedUrl)
            ->assertOk();
    }

    public function test_executable_and_oversized_attachments_are_rejected(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($customer);

        $this->post('/api/v1/support/tickets/messages', [
            'category' => 'technical_issue',
            'priority' => 'medium',
            'subject' => 'Bad executable',
            'message' => 'Should fail',
            'attachment' => UploadedFile::fake()->create('malware.exe', 20, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);

        $this->post('/api/v1/support/tickets/messages', [
            'category' => 'technical_issue',
            'priority' => 'medium',
            'subject' => 'Too large',
            'message' => 'Should fail size',
            'attachment' => UploadedFile::fake()->create('huge.pdf', 30_000, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);

        $this->assertSame(0, SupportMessage::query()->count());
    }
}
