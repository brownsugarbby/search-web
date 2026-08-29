<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Browsing a category.
 *
 * This is the one listing that stays on regardless of the results-page flag:
 * it is not a search result, it is the catalog itself, and a visitor who does
 * not yet know a keyword needs some way in.
 */
class CategoryController extends Controller
{
    public function __invoke(string $slug): View
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw new NotFoundHttpException;
        }

        return view('category', [
            'category' => $category,
            'links' => $category->links()
                ->public()
                ->orderByDesc('click_count')
                ->orderByDesc('weight')
                ->paginate(24),
        ]);
    }
}
