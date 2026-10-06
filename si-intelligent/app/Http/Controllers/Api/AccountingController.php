<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct(private readonly AccountingService $accountingService)
    {
    }

    public function accounts(Request $request)
    {
        abort_unless($request->user()->hasPermission('accounting.view'), 403);

        $accounts = ChartOfAccount::where('company_id', $request->user()->company_id)->orderBy('code')->get();

        return response()->json($accounts->map(fn ($a) => [
            'id' => $a->id, 'code' => $a->code, 'label' => $a->label,
            'class' => $a->class, 'balance' => $a->balance(),
        ]));
    }

    public function entries(Request $request)
    {
        abort_unless($request->user()->hasPermission('accounting.view'), 403);

        $entries = JournalEntry::where('company_id', $request->user()->company_id)
            ->with('lines.account')
            ->orderByDesc('entry_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json($entries);
    }

    public function ledger(Request $request, string $code)
    {
        abort_unless($request->user()->hasPermission('accounting.view'), 403);

        $ledger = $this->accountingService->getLedger(
            $request->user()->company_id,
            $code,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        return response()->json($ledger);
    }
}
