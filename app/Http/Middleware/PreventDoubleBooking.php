<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDoubleBooking
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $ticketId = $request->route('id') ?? $request->input('ticket_id');
        $userId = $request->user()->id;

        if ($ticketId) {
            $existingBooking = \App\Models\Booking::where('user_id', $userId)
                ->where('ticket_id', $ticketId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($existingBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active booking for this ticket',
                ], Response::HTTP_CONFLICT);
            }
        }

        return $next($request);
    }
}
