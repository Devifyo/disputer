<?php

namespace App\Services;

use App\Models\LetterTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LetterTemplateService
{
    /**
     * Fetch active templates with search and pagination.
     *
     * @param string|null $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getActiveTemplates(?string $search = null, int $perPage = 9): LengthAwarePaginator
    {
        $query = LetterTemplate::with('category')
            ->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($catQ) use ($search) {
                      $catQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Use paginate() instead of get()
        return $query->latest()->paginate($perPage);
    }
}