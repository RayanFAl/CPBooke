<?php

namespace App\Modules\Admin\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController
{
    /**
     * Display the finance management shell.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/finance/pages/Index');
    }
}