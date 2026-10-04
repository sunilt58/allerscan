<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuggestionRequest;
use App\Http\Resources\SuggestionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SuggestionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SuggestionResource::collection($request->user()->suggestions()->with('product')->latest('id')->limit(30)->get());
    }

    public function store(StoreSuggestionRequest $request): SuggestionResource
    {
        return new SuggestionResource($request->user()->suggestions()->create($request->suggestion()));
    }
}
