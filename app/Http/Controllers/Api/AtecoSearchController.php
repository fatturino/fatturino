<?php

namespace App\Http\Controllers\Api;

use App\Enums\AtecoCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtecoSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $results = AtecoCode::options($query);
        $payload = array_map(
            fn (array $result) => [
                'code' => $result['id'],
                'description' => $result['name'],
            ],
            array_slice($results, 0, 50)
        );

        return response()->json($payload);
    }
}
