<?php

namespace App\Http\Controllers;

use App\Support\Platform\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerSupportChatController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isAdminAccount()) {
            return redirect()->route('admin.support.index');
        }

        if (! PlatformSettings::supportChatEnabled()) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Customer support chat is temporarily unavailable.');
        }

        return Inertia::render('Support/Chat', [
            'chat' => [
                'selected_ticket_id' => $request->integer('ticket') ?: null,
                'routes' => [
                    'tickets_index' => '/api/v1/support/chat/tickets',
                    'tickets_create_or_reuse' => '/api/v1/support/tickets/messages',
                ],
            ],
        ]);
    }
}
