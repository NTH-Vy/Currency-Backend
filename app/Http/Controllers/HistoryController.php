<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConversionHistory;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->user_id;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, (int) $request->query('per_page', 10));

        $query = ConversionHistory::where('user_id', $userId)
            ->orderBy('history_id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
