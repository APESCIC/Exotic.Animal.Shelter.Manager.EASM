<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(private GlobalSearch $search) {}

    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());

        return view('search.index', [
            'q' => $q,
            'groups' => $this->search->search($q),
            'minLength' => GlobalSearch::MIN_LENGTH,
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        return response()->json([
            'query' => $q,
            'groups' => $this->search->search($q),
        ]);
    }
}
