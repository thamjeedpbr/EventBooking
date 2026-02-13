<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Booking $booking)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->booking->ticket->event;
        $ticket = $this->booking->ticket;

        return (new MailMessage)
            ->subject('Booking Confirmed - ' . $event->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your booking has been confirmed successfully.')
            ->line('**Booking Reference:** ' . $this->booking->booking_reference)
            ->line('**Event:** ' . $event->title)
            ->line('**Date:** ' . $event->date->format('F j, Y g:i A'))
            ->line('**Location:** ' . $event->location)
            ->line('**Ticket Type:** ' . $ticket->type)
            ->line('**Quantity:** ' . $this->booking->quantity)
            ->line('**Total Amount:** $' . number_format($this->booking->total_amount, 2))
            ->line('Thank you for booking with us!')
            ->line('Please keep this booking reference for your records.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $event = $this->booking->ticket->event;

        return [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'event_title' => $event->title,
            'event_date' => $event->date->toDateTimeString(),
            'event_location' => $event->location,
            'ticket_type' => $this->booking->ticket->type,
            'quantity' => $this->booking->quantity,
            'total_amount' => $this->booking->total_amount,
            'message' => 'Your booking for ' . $event->title . ' has been confirmed.',
        ];
    }
}
