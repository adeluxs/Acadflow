<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AdminLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLookupController extends Controller
{
    public function __invoke(Request $request, AdminLookupService $lookups): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(AdminLookupService::KINDS)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'university_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'course_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $options = $lookups->query($request->user(), $data['kind'], $data)->simplePaginate(20);

        return response()->json([
            'data' => $options->getCollection()->map(fn ($model) => $lookups->option($model)),
            'next_page' => $options->hasMorePages() ? $options->currentPage() + 1 : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
