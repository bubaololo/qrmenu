<?php

namespace App\Http\Controllers\DiningTables;

use App\Actions\StoreDiningTableAction;
use App\Actions\UpdateDiningTableAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiningTables\StoreDiningTableRequest;
use App\Http\Requests\DiningTables\UpdateDiningTableRequest;
use App\Http\Resources\DiningTables\DiningTableResource;
use App\Models\DiningTable;
use App\Models\Hall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DiningTableController extends Controller
{
    public function index(Hall $hall): AnonymousResourceCollection
    {
        Gate::authorize('view', $hall);

        return DiningTableResource::collection(
            $hall->tables()->get()
        );
    }

    public function store(StoreDiningTableRequest $request, Hall $hall): JsonResponse
    {
        Gate::authorize('create', [DiningTable::class, $hall]);

        $table = app(StoreDiningTableAction::class)($hall, $request->toData());

        return (new DiningTableResource($table))->response()->setStatusCode(201);
    }

    public function show(DiningTable $diningTable): DiningTableResource
    {
        Gate::authorize('view', $diningTable);

        return new DiningTableResource($diningTable);
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
}
