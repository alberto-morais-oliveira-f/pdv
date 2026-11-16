<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Controllers\Api;

use Am2tec\Pdv\Http\Requests\StoreTerminalRequest;
use Am2tec\Pdv\Http\Requests\UpdateTerminalRequest;
use Am2tec\Pdv\Http\Resources\TerminalResource;
use Am2tec\Pdv\Models\Terminal;
use Am2tec\Pdv\Services\TerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TerminalController extends Controller
{
    public function __construct(private readonly TerminalService $terminals) {}

    public function index(): AnonymousResourceCollection
    {
        return TerminalResource::collection($this->terminals->list());
    }

    public function store(StoreTerminalRequest $request): JsonResponse
    {
        $terminal = $this->terminals->create($request->validated());

        return (new TerminalResource($terminal))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTerminalRequest $request, Terminal $terminal): TerminalResource
    {
        return new TerminalResource($this->terminals->update($terminal, $request->validated()));
    }

    public function destroy(Terminal $terminal): Response
    {
        $this->terminals->delete($terminal);

        return response()->noContent();
    }
}
