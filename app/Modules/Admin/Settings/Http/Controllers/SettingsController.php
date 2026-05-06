<?php

namespace App\Modules\Admin\Settings\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController
{
    /**
     * Display the settings shell.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/settings/pages/Index');
    }
}