<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Api\ContractController as SupportContractController;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قراردادهای اپِ مشتری — فقط قراردادهای شرکتِ خودِ کاربر. از همان presenterهای
 * پشتیبان استفاده می‌کند تا نمایش یکسان بماند.
 */
class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $contracts = Contract::with(['customer', 'plan'])
            ->where('customer_id', $user->customer_id)
            ->latest('start_date')->paginate(20);

        return response()->json([
            'data' => collect($contracts->items())->map(fn (Contract $c) => SupportContractController::row($c))->all(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page'    => $contracts->lastPage(),
                'total'        => $contracts->total(),
            ],
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        $user = $request->user();
        abort_unless($contract->customer_id === $user->customer_id, 404);

        return response()->json(SupportContractController::detail($contract));
    }
}
