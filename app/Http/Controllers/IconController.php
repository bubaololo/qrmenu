<?php

namespace App\Http\Controllers;

use App\Models\Icon;
use Illuminate\Http\JsonResponse;

class IconController extends Controller
{
    /**
     * List all renderable icons.
     *
     * Returns the alphabetically-sorted set of icon names that the frontend
     * can reference via `<use href="/menu-sprite.svg#<name>"/>`. Names are
     * stable identifiers; clients should treat them as opaque strings.
     *
     * @response array{data: string[]}
     */
    public function index(): JsonResponse
    {
        $names = Icon::query()
            ->whereNotNull('svg')
            ->where('svg', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return response()->json(['data' => $names]);
    }
}
