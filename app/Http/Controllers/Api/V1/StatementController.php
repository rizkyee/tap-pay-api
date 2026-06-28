<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Statement\GenerateStatementRequest;
use App\Http\Resources\StatementResource;
use App\Models\Statement;
use App\Services\StatementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StatementService $statements,
    ) {
    }

    // POST /api/statements  (juga dipakai POST /api/open-payment/statements)
    public function store(GenerateStatementRequest $request): JsonResponse
    {
        $statement = $this->statements->generate(
            $request->user(),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('type', Statement::TYPE_ALL),
        );

        return $this->successResponse('Statement berhasil dibuat.', [
            'statement' => new StatementResource($statement),
        ], 201);
    }

    // GET /api/statements
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->statements->paginateForUser($request->user());

        return $this->successResponse(
            'Daftar statement.',
            StatementResource::collection($paginated)->response()->getData(true),
        );
    }

    // GET /api/statements/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        $statement = $this->statements->findForUser($request->user(), $id);
        $data = $this->statements->buildStatementData($request->user(), $statement);

        return $this->successResponse('Detail statement.', ['statement' => $data]);
    }

    // GET /api/statements/{id}/download  (data lengkap siap dirender PDF)
    public function download(Request $request, int $id): JsonResponse
    {
        $statement = $this->statements->findForUser($request->user(), $id);
        $data = $this->statements->buildStatementData($request->user(), $statement);

        return $this->successResponse('Data statement untuk download.', [
            'format' => 'statement-payload',
            'statement' => $data,
        ]);
    }
}
