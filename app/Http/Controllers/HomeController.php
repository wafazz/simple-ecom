<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('storefront.home', [
            'categories' => Category::query()->active()->orderBy('name')->get(),
        ]);
    }
}
