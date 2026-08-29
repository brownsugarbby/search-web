<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return view('page', ['page' => $page]);
    }
}
