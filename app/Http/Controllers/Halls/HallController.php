<?php

namespace App\Http\Controllers\Halls;

use App\Actions\StoreHallAction;
use App\Actions\UpdateHallAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Halls\StoreHallRequest;
use App\Http\Requests\Halls\UpdateHallRequest;
use App\Http\Resources\Halls\HallResource;
use App\Models\Hall;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class HallController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        return HallResource::collection(
            $restaurant->halls()->with('tables')->get()
        );
    }

    public function store(StoreHallRequest $request, Restaurant $restaurant): JsonResponse
    {
        Gate::authorize('create', [Hall::class, $restaurant]);

        $hall = app(StoreHallAction::class)($restaurant, $request->toData());

        return (new HallResource($hall))->response()->setStatusCode(201);
    }

    public function show(Hall $hall): HallResource
    {
        Gate::authorize('view', $hall);

        return new HallResource($hall->load('tables'));
    }

    public function update(UpdateHallRequest $request, Hall $hall): HallResource
    {
        Gate::authorize('update', $hall);

        $hall = app(UpdateHallAction::class)($hall, $request->toData());

        return new HallResource($hall);
    }

    public function destroy(Hall $hall): JsonResponse
    {
        Gate::authorize('delete', $hall);

        $hall->delete();

        return response()->json(null, 204);
    }
}
