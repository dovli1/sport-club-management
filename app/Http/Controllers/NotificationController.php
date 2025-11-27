<?php
// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'admin' || $user->role === 'coach') {
            // Admins and coaches see all notifications
            $notifications = Notification::with('creator')
                                        ->orderBy('created_at', 'desc')
                                        ->get();
        } else {
            // Players see notifications targeted to them
            $notifications = Notification::where('is_active', true)
                ->where(function($query) use ($user) {
                    $query->where('target_role', 'all')
                          ->orWhere('target_role', $user->role);
                })
                ->with('creator')
                ->orderBy('created_at', 'desc')
                ->get();

            // Mark as read status
            foreach ($notifications as $notification) {
                $readStatus = $notification->users()
                    ->where('user_id', $user->id)
                    ->first();
                
                $notification->is_read = $readStatus ? true : false;
                $notification->read_at = $readStatus ? $readStatus->pivot->read_at : null;
            }
        }

        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,success,urgent',
            'target_role' => 'required|in:all,admin,coach,player',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $notification = Notification::create([
            'created_by' => auth()->id(),
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type,
            'target_role' => $request->target_role,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Notification created successfully',
            'notification' => $notification
        ], 201);
    }

    public function show($id)
    {
        $notification = Notification::with('creator')->findOrFail($id);
        return response()->json($notification);
    }

    public function update(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'type' => 'sometimes|in:info,warning,success,urgent',
            'target_role' => 'sometimes|in:all,admin,coach,player',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $notification->update($request->all());

        return response()->json([
            'message' => 'Notification updated successfully',
            'notification' => $notification
        ]);
    }

    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $user = auth()->user();

        $notification->users()->syncWithoutDetaching([
            $user->id => ['read_at' => now()]
        ]);

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        
        $notifications = Notification::where('is_active', true)
            ->where(function($query) use ($user) {
                $query->where('target_role', 'all')
                      ->orWhere('target_role', $user->role);
            })
            ->get();

        foreach ($notifications as $notification) {
            $notification->users()->syncWithoutDetaching([
                $user->id => ['read_at' => now()]
            ]);
        }

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    public function getUnreadCount()
    {
        $user = auth()->user();
        
        $unreadCount = Notification::where('is_active', true)
            ->where(function($query) use ($user) {
                $query->where('target_role', 'all')
                      ->orWhere('target_role', $user->role);
            })
            ->whereDoesntHave('users', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->count();

        return response()->json(['unread_count' => $unreadCount]);
    }
}