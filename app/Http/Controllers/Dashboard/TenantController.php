<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\UpdateTenantAction;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly CreateTenantAction $createTenant,
        private readonly UpdateTenantAction $updateTenant,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.tenants.index', ['tenants' => $this->tenants->all()]);
    }

    public function create(): View
    {
        return view('dashboard.tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $this->createTenant->execute($data['name'], $data['slug']);

        return redirect()->route('dashboard.tenants.index')->with('status', t('messages.tenants.create'));
    }

    public function edit(int $tenantId): View
    {
        $tenant = $this->tenants->findById($tenantId);

        abort_if($tenant === null, 404);

        return view('dashboard.tenants.edit', ['tenant' => $tenant]);
    }

    public function update(Request $request, int $tenantId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:pending,active,suspended'],
        ]);

        $this->updateTenant->execute($tenantId, $data['name'], $data['status']);

        return redirect()->route('dashboard.tenants.index')->with('status', t('messages.tenants.edit'));
    }
}
