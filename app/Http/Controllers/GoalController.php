<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoalController extends Controller
{
    /**
     * @group Goals
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return GoalResource::collection(
            request()->user()
                ->goals()
                ->with([
                    'currency',
                    'contributions' => fn ($query) => $query
                        ->with(['transaction.category', 'transaction.wallet.currency', 'transaction.currency', 'transaction.reportingCurrency'])
                        ->orderByDesc('occurred_on')
                        ->orderByDesc('id'),
                ])
                ->withSum('contributions as current_amount', 'amount')
                ->withCount('contributions')
                ->orderByRaw('completed_at is null desc')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @group Goals
     * @authenticated
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($request->validated());

        return (new GoalResource($goal->load('currency')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @group Goals
     * @authenticated
     */
    public function update(UpdateGoalRequest $request, Goal $goal): GoalResource
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $goal->fill($request->validated())->save();

        return new GoalResource(
            $goal->refresh()
                ->load('currency')
                ->loadSum('contributions as current_amount', 'amount')
                ->loadCount('contributions')
        );
    }

    /**
     * @group Goals
     * @authenticated
     */
    public function destroy(Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === request()->user()->id, 404);

        if ($goal->contributions()->exists()) {
            return response()->json([
                'message' => 'Goals with contributions cannot be deleted.',
            ], 422);
        }

        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully.',
        ]);
    }
}
