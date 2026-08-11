<?php

namespace App\Domains\Nexus\Business\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Interfaces\Http\Requests\RegisterBusinessRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Receives the request, calls RegisterBusinessAction, then creates the
 * Business owner's own login credential and its session — controller
 * stays thin (Controllers rule): no business logic here, only the HTTP
 * adaptation RegisterBusinessAction itself doesn't own (file storage,
 * the owner credential, and establishing the session).
 */
class RegisterBusinessController extends Controller
{
    public function __construct(
        private readonly RegisterBusinessAction $registerBusiness,
    ) {
    }

    public function create(): View
    {
        return view('nexus::business.register', ['industries' => Industry::cases(), 'types' => BusinessType::cases()]);
    }

    public function store(RegisterBusinessRequest $request): RedirectResponse
    {
        $logoPath = $request->file('logo')?->store('nexus/business-logos', 'public');
        $documentPaths = collect($request->file('documents'))
            ->map(fn ($file) => $file->store('nexus/business-documents', 'public'))
            ->all();

        $business = $this->registerBusiness->execute(
            nameFa: $request->string('name_fa')->toString(),
            nameEn: $request->string('name_en')->toString(),
            type: BusinessType::from($request->string('type')->toString()),
            industry: Industry::from($request->string('industry')->toString()),
            logoPath: $logoPath,
            documents: $documentPaths ?: null,
        );

        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => $request->string('owner_name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        Auth::guard('business')->login($owner);
        $request->session()->regenerate();

        return redirect()->route('nexus.business.dashboard')->with('status', t('messages.nexus.business.registered'));
    }
}
