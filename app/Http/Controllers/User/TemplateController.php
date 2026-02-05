<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\LetterTemplateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    protected $templateService;

    public function __construct(LetterTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display the library of letter templates.
     */
    public function index(Request $request): View
    {
        // Now returns a Paginator instance, not a Collection
        $templates = $this->templateService->getActiveTemplates(
            $request->get('search'), 
            9
        );

        return view('user.templates.index', compact('templates'));
    }


    public function search(Request $request)
    {
        $query = $request->get('query');

        $templates = \App\Models\LetterTemplate::with('category')
            ->where('is_active', true)
            ->when($query, function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhereHas('category', function ($catQ) use ($query) {
                      $catQ->where('name', 'like', "%{$query}%");
                  });
            })
            ->latest()
            ->limit(10) // Limit results for speed
            ->get(['id', 'institution_category_id', 'title', 'content']); // Select only needed columns

        // Transform data for the frontend
        $data = $templates->map(function ($t) {
            return [
                'id' => $t->id,
                'title' => $t->title,
                'category' => $t->category->name ?? 'General',
                'content' => $t->content
            ];
        });

        return response()->json($data);
    }
}