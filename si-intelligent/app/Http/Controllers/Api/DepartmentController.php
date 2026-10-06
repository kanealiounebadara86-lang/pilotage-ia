<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Department::where('company_id', $request->user()->company_id)->withCount('employees')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('hr.manage'), 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $department = Department::create([...$validated, 'company_id' => $request->user()->company_id]);

        return response()->json($department, 201);
    }
}
