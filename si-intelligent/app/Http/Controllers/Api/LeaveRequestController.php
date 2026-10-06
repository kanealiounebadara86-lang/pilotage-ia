<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveService $leaveService)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.view'), 403);

        $leaves = LeaveRequest::whereHas('employee', fn ($q) => $q->where('company_id', $request->user()->company_id))
            ->with('employee')
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 30));

        return response()->json($leaves);
    }

    public function store(StoreLeaveRequestRequest $request)
    {
        $leave = $this->leaveService->requestLeave($request->validated());

        return response()->json($leave->load('employee'), 201);
    }

    public function review(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);

        $validated = $request->validate(['status' => ['required', 'in:approuvee,refusee']]);

        $leave = $this->leaveService->review($leaveRequest, $validated['status'], $request->user());

        return response()->json($leave);
    }
}
