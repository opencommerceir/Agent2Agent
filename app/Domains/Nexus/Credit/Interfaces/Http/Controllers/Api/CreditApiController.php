<?php

namespace App\Domains\Nexus\Credit\Interfaces\Http\Controllers\Api;

use App\Domains\Nexus\Credit\Application\Actions\GetCreditBalanceAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API (Phase 9/M2) — `credit.read` scope. Reuses
 * GetCreditBalanceAction unchanged, the same lookup nexus.credit.balance
 * (free, Phase 3/M2) already serves.
 */
class CreditApiController extends Controller
{
    public function __construct(
        private readonly GetCreditBalanceAction $getCreditBalance,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $this->getCreditBalance->execute((int) $request->attributes->get('nexus_business_id'));

        return response()->json(['data' => $data->toArray()]);
    }
}
