<?php

declare(strict_types=1);

namespace Am2tec\Pdv\Http\Controllers\Api;

use Am2tec\Pdv\Contracts\CatalogSearcher;
use Am2tec\Pdv\Data\CatalogItemData;
use Am2tec\Pdv\Http\Requests\SearchCatalogRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogSearcher $catalog) {}

    public function index(SearchCatalogRequest $request): JsonResponse
    {
        $items = $this->catalog
            ->search($request->searchTerm(), $request->searchLimit())
            ->map(fn (CatalogItemData $item): array => $item->toArray())
            ->values();

        return response()->json($items);
    }
}
