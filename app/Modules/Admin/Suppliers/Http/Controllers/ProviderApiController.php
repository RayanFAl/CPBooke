<?php

namespace App\Modules\Admin\Suppliers\Http\Controllers;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Modules\Admin\Suppliers\Http\Requests\SyncProviderServicesRequest;
use App\Modules\Admin\Suppliers\Http\Requests\UpsertProviderApiConfigRequest;
use App\Modules\Providers\Services\ProviderApiConfigPresenter;
use App\Modules\Providers\Services\ProviderApiConfigService;
use App\Modules\Providers\Services\ProviderConnectionTestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderApiController
{
    public function __construct(
        private readonly ProviderApiConfigService $apiConfigService,
        private readonly ProviderConnectionTestService $connectionTestService,
        private readonly ProviderApiConfigPresenter $presenter,
    ) {
    }

    public function upsertConfig(UpsertProviderApiConfigRequest $request, Provider $supplier): RedirectResponse
    {
        $config = $this->apiConfigService->upsert(
            $supplier,
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', ucfirst($config->environment).' API configuration saved.');
    }

    public function disableConfig(Request $request, Provider $supplier, string $environment): RedirectResponse
    {
        $this->apiConfigService->disable(
            $supplier,
            $environment,
            $request->user(),
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', ucfirst($environment).' API configuration disabled.');
    }

    public function syncServices(SyncProviderServicesRequest $request, Provider $supplier): RedirectResponse
    {
        $this->apiConfigService->syncServices(
            $supplier,
            $request->validated('services'),
            $request->user(),
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', 'Provider services updated.');
    }

    public function testConnection(Request $request, Provider $supplier, string $environment): RedirectResponse
    {
        $config = ProviderApiConfig::query()
            ->where('provider_id', $supplier->id)
            ->where('environment', strtolower($environment))
            ->firstOrFail();

        $result = $this->connectionTestService->test($config);

        $message = $result['success']
            ? 'Connection successful (HTTP '.$result['http_status'].', '.$result['latency_ms'].'ms).'
            : 'Connection failed: '.$result['message'];

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with($result['success'] ? 'success' : 'error', $message);
    }

    public function auditCredentialView(Request $request, Provider $supplier, string $environment): RedirectResponse
    {
        $this->apiConfigService->logCredentialAccess(
            $supplier,
            $request->user(),
            strtolower($environment),
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('info', 'Credential access logged.');
    }
}
