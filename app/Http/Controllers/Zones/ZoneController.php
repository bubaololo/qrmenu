<?php

namespace App\Http\Controllers\Zones;

use App\Actions\StoreZoneAction;
use App\Actions\UpdateZoneAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Zones\StoreZoneRequest;
use App\Http\Requests\Zones\UpdateZoneRequest;
use App\Http\Resources\Zones\ZoneResource;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ZoneController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        return ZoneResource::collection(
            $restaurant->zones()->with('tables.tableShape')->get()
        );
    }

    public function store(StoreZoneRequest $request, Restaurant $restaurant): JsonResponse
    {
        Gate::authorize('create', [Zone::class, $restaurant]);

        $zone = app(StoreZoneAction::class)($restaurant, $request->toData());

        return (new ZoneResource($zone))->response()->setStatusCode(201);
    }

    public function show(Zone $zone): ZoneResource
    {
        Gate::authorize('view', $zone);

        return new ZoneResource($zone->load('tables.tableShape'));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): ZoneResource
    {
        Gate::authorize('update', $zone);

        $zone = app(UpdateZoneAction::class)($zone, $request->toData());

        return new ZoneResource($zone);
    }

    public function destroy(Zone $zone): JsonResponse
    {
        Gate::authorize('delete', $zone);

        $zone->delete();

        return response()->json(null, 204);
    }
}
