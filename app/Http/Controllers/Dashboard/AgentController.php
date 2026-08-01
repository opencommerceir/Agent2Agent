<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Application\Actions\ChangeAgentStatusAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\Actions\UpdateAgentAction;
use App\Core\Domain\Repositories\AgentRepositoryInterface;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\AgentStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentRepositoryInterface $agents,
        private readonly TenantRepositoryInterface $tenants,
        private readonly RegisterAgentAction $registerAgent,
        private readonly UpdateAgentAction $updateAgent,
        private readonly ChangeAgentStatusAction $changeAgentStatus,
    ) {
    }

    public function index(Request $request): View
    {
        $agents = $this->agents->all();
        $tenantId = $request->integer('tenant_id') ?: null;

        if ($tenantId !== null) {
            $agents = array_values(array_filter($agents, fn ($agent) => $agent->tenantId() === $tenantId));
        }

        return view('dashboard.agents.index', [
            'agents' => $agents,
            'tenants' => $this->tenants->all(),
            'selectedTenantId' => $tenantId,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.agents.create', ['tenants' => $this->tenants->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'organization_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:shopping,analytics,customer_service,custom'],
        ]);

        $this->registerAgent->execute($data['tenant_id'], $data['organization_id'], $data['name'], $data['type']);

        return redirect()->route('dashboard.agents.index')->with('status', t('messages.agents.create'));
    }

    public function edit(int $agentId): View
    {
        $agent = $this->agents->findById($agentId);

        abort_if($agent === null, 404);

        return view('dashboard.agents.edit', ['agent' => $agent]);
    }

    public function update(Request $request, int $agentId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:shopping,analytics,customer_service,custom'],
        ]);

        $this->updateAgent->execute($agentId, $data['name'], $data['type']);

        return redirect()->route('dashboard.agents.index')->with('status', t('messages.agents.edit'));
    }

    public function suspend(int $agentId): RedirectResponse
    {
        $this->changeAgentStatus->execute($agentId, AgentStatus::Suspended);

        return redirect()->route('dashboard.agents.index')->with('status', t('messages.agents.suspend'));
    }

    public function activate(int $agentId): RedirectResponse
    {
        $this->changeAgentStatus->execute($agentId, AgentStatus::Active);

        return redirect()->route('dashboard.agents.index')->with('status', t('messages.agents.activate'));
    }
}
