<?php

namespace App\Modules\Admin\Support\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController
{
    /**
     * Display the support management shell.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/support/pages/Index');
    }
}