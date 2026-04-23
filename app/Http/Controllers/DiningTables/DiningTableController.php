<?php

namespace App\Http\Controllers\DiningTables;

use App\Actions\GenerateQrCode;
use App\Actions\StoreDiningTableAction;
use App\Actions\UpdateDiningTableAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiningTables\StoreDiningTableRequest;
use App\Http\Requests\DiningTables\UpdateDiningTableRequest;
use App\Http\Resources\DiningTables\DiningTableResource;
use App\Models\DiningTable;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DiningTableController extends Controller
{
    public function index(Zone $zone): AnonymousResourceCollection
    {
        Gate::authorize('view', $zone);

        return DiningTableResource::collection(
            $zone->tables()->with('tableShape')->get()
        );
    }

    public function store(StoreDiningTableRequest $request, Zone $zone): JsonResponse
    {
        Gate::authorize('create', [DiningTable::class, $zone]);

        $table = app(StoreDiningTableAction::class)($zone, $request->toData());

        return (new DiningTableResource($table))->response()->setStatusCode(201);
    }

    public function show(DiningTable $diningTable): DiningTableResource
    {
        Gate::authorize('view', $diningTable);

        return new DiningTableResource($diningTable->load('tableShape'));
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $diningTable): DiningTableResource
    {
        Gate::authorize('update', $diningTable);

        $table = app(UpdateDiningTableAction::class)($diningTable, $request->toData());

        return new DiningTableResource($table);
    }

    public function destroy(DiningTable $diningTable): JsonResponse
    {
        Gate::authorize('delete', $diningTable);

        $diningTable->delete();

        return response()->json(null, 204);
    }

    /**
     * Return a PNG QR code that links to the restaurant's public menu scoped to this table.
     *
     * The encoded URL is `{app_url}/{restaurant.uniqid}/t/{table.uniqid}`.
     */
    public function qr(DiningTable $diningTable, GenerateQrCode $generateQr): Response
    {
        Gate::authorize('view', $diningTable);

        $restaurant = $diningTable->zone->restaurant;
        $url = config('app.url').'/'.$restaurant->uniqid.'/t/'.$diningTable->uniqid;

        return $generateQr($url);
    }
}
