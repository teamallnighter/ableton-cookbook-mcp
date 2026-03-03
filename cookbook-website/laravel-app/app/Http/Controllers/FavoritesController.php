<?php

namespace App\Http\Controllers;

use App\Models\RackFavorite;
use Illuminate\Support\Facades\Auth;

class FavoritesController extends Controller
{
    public function index()
    {
        $favorites = RackFavorite::where('user_id', Auth::id())
            ->with(['rack.user'])
            ->latest()
            ->paginate(12);

        return view('favorites.index', compact('favorites'));
    }
}
