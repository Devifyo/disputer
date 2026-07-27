<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** In-app notifications for the customer SPA - list, unread count, mark read. */
class NotificationApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return response()->json(['data' => [
            'unread'        => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(25)->get()
                ->map(fn ($n) => [
                    'id'          => $n->id,
                    'title'       => $n->data['title'] ?? 'Update',
                    'description' => $n->data['description'] ?? '',
                    'url'         => $n->data['claim_url'] ?? null,
                    'read'        => $n->read_at !== null,
                    'at'          => $n->created_at->diffForHumans(),
                ]),
        ]]);
    }

    public function markRead(Request $request)
    {
        $user = Auth::user();

        $request->validate(['id' => 'nullable|string']);

        if ($request->filled('id')) {
            $user->notifications()->where('id', $request->input('id'))->update(['read_at' => now()]);
        } else {
            $user->unreadNotifications->markAsRead();
        }

        return response()->json(['success' => true]);
    }
}
