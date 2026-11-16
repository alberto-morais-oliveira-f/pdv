<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Controllers\Api;

use Am2tec\Pdv\Exceptions\SaleFulfillmentFailed;
use Am2tec\Pdv\Http\Requests\CompleteSaleRequest;
use Am2tec\Pdv\Http\Requests\ListSalesRequest;
use Am2tec\Pdv\Http\Resources\SaleResource;
use Am2tec\Pdv\Models\Sale;
use Am2tec\Pdv\Services\SaleCancellationService;
use Am2tec\Pdv\Services\SaleCompletionService;
use Am2tec\Pdv\Services\SaleHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleCompletionService $sales,
        private readonly SaleCancellationService $cancellations,
        private readonly SaleHistoryService $history,
    ) {}

    public function index(ListSalesRequest $request): AnonymousResourceCollection
    {
        return SaleResource::collection($this->history->list($request->criteria()));
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load(['items', 'payments']));
    }

    public function store(CompleteSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->sales->complete($request->saleData());
        } catch (SaleFulfillmentFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return (new SaleResource($sale))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Cancels the sale (soft: the row is kept with status `cancelled`).
     */
    public function destroy(Sale $sale): SaleResource
    {
        return new SaleResource($this->cancellations->cancel($sale));
    }
}
