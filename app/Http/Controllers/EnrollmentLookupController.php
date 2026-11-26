<?php

namespace App\Http\Controllers;

use App\Models\IndigenousGroup;
use App\Models\MotherTongue;
use App\Models\Religion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentLookupController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        $models = [
            'mother-tongues' => MotherTongue::class,
            'religions' => Religion::class,
            'indigenous-groups' => IndigenousGroup::class,
        ];

        abort_unless(isset($models[$type]), 404);

        $search = $request->string('search')->trim()->toString();

        $results = $models[$type]::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);

        return response()->json($results);
    }
}
