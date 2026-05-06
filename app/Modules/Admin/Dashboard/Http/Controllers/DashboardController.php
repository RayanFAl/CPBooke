<?php

namespace App\Modules\Admin\Dashboard\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display the admin dashboard shell.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/dashboard/pages/Index');
    }
}