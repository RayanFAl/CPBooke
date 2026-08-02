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

    public function test_attachment_is_stored_privately_and_served_only_via_signed_url(): void
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
            'subject' => 'Private attachment',
            'message' => 'Please keep this private.',
            'attachment' => UploadedFile::fake()->create('secret.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $message = SupportMessage::query()->firstOrFail();

        Storage::disk('local')->assertExists($message->attachment_path);
        Storage::disk('public')->assertMissing($message->attachment_path);

        $this->get('/support/attachments/'.$message->id)->assertForbidden();

        $signedUrl = URL::temporarySignedRoute(
            'support.attachments.show',
            now()->addMinutes(5),
            ['message' => $message->id],
        );

        $this->get($signedUrl)->assertOk();
    }

    public function test_unsigned_or_tampered_attachment_urls_are_rejected(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-SEC-1',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Security attachment',
            'description' => 'Security coverage.',
        ]);

        $path = UploadedFile::fake()
            ->create('locked.pdf', 16, 'application/pdf')
            ->store(SupportAttachmentStorage::DIRECTORY, 'local');

        $message = SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'file',
            'is_internal' => false,
            'attachment_path' => $path,
            'attachment_name' => 'locked.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 16,
        ]);

        $this->get(route('support.attachments.show', $message))->assertForbidden();

        $expired = URL::temporarySignedRoute(
            'support.attachments.show',
            now()->subMinute(),
            ['message' => $message->id],
        );

        $this->get($expired)->assertForbidden();
    }
}
