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
}