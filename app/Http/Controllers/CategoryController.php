<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * @group Categories
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            request()->user()
                ->categories()
                ->orderBy('is_archived')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @group Categories
     * @authenticated
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @group Categories
     * @authenticated
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        $category->fill($request->validated())->save();

        return new CategoryResource($category->refresh());
    }

    /**
     * @group Categories
     * @authenticated
     */
    public function destroy(Category $category): JsonResponse
    {
        abort_unless($category->user_id === request()->user()->id, 404);

        if ($category->transactions()->exists()) {
            $category->forceFill(['is_archived' => true])->save();

            return response()->json([
                'message' => 'Category archived because it has existing transactions.',
            ]);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
