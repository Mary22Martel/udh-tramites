<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = UserNotification::where('user_id', $request->user()->id)->latest('id')->limit(40)->get();

        return [
            'data' => $items,
            'unread' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ];
    }

    public function read(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return ['ok' => true];
    }

    public function readAll(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ['ok' => true];
    }
}
