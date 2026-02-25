<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $type = $request->get('type');

        // Fetch attachments belonging to cases owned by the current user
        $query = Attachment::with(['case.institution']) // Eager load related case info
            ->whereHas('case', function (Builder $q) {
                $q->where('user_id', Auth::id());
            });

        // 1. Search Logic (Filename or Case Reference)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhereHas('case', function ($sq) use ($search) {
                      $sq->where('case_reference_id', 'like', "%{$search}%");
                  });
            });
        }

        // 2. File Type Filter
        if (!empty($type)) {
            $query->where(function ($q) use ($type) {
                if ($type === 'image') {
                    $q->where('mime_type', 'like', 'image/%');
                } elseif ($type === 'pdf') {
                    $q->where('mime_type', 'like', '%pdf%');
                } elseif ($type === 'doc') {
                    $q->where('mime_type', 'like', '%word%')
                      ->orWhere('mime_type', 'like', '%document%');
                }
            });
        }

        // Paginate (15 items per page)
        $documents = $query->latest()->paginate(15);

        return view('user.documents.index', compact('documents'));
    }

    public function showPublic($encryptedId)
    {  
        $id = decrypt_id($encryptedId);
        if (!$id) {
            abort(404);
        }
        // Find the attachment (and related case info)
        $attachment = Attachment::with('case')->findOrFail($id);

        // Check if file still exists
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'File not found on server.');
        }

        return view('user.documents.public_viewer', compact('attachment'));
    }

    public function downloadSecure($encryptedId)
    {
        $id = decrypt_id($encryptedId);
        $attachment = Attachment::findOrFail($id);
        $path = $attachment->file_path;

        // CHECK 1: Try the 'public' disk (Standard)
        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path));
        }

        // CHECK 2: Try the 'local' disk (Fallback)
        // Sometimes files get saved here by mistake if 'public' wasn't specified during upload
        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path));
        }

        // CHECK 3: Try removing 'public/' from the path string
        // If DB has "public/attachments/..." but disk is already inside public
        $cleanPath = str_replace('public/', '', $path);
        if (Storage::disk('public')->exists($cleanPath)) {
            return response()->file(Storage::disk('public')->path($cleanPath));
        }

        // If we reach here, the file is physically missing from the server
        \Log::error("File missing for Attachment ID {$id}: {$path}");
        abort(404, 'File not found on server.');
    }
}