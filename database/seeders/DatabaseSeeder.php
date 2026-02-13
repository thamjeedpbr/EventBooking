<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Seeding Event Booking System...');

        // Create admins
        $this->command->info('Creating admins...');
        $admin1 = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $admin2 = User::factory()->admin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create organizers
        $this->command->info('Creating organizers...');
        $organizer1 = User::factory()->organizer()->create([
            'name' => 'Event Organizer',
            'email' => 'organizer@example.com',
            'password' => Hash::make('password'),
        ]);

        $organizer2 = User::factory()->organizer()->create([
            'name' => 'John Organizer',
            'email' => 'john.organizer@example.com',
            'password' => Hash::make('password'),
        ]);

        $organizer3 = User::factory()->organizer()->create([
            'name' => 'Jane Organizer',
            'email' => 'jane.organizer@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create customers
        $this->command->info('Creating customers...');
        $customer1 = User::factory()->customer()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->customer()->count(9)->create();
        $customers = User::where('role', 'customer')->get();

        // Create events
        $this->command->info('Creating events...');
        $event1 = Event::factory()->create([
            'title' => 'Laravel Conference 2026',
            'description' => 'Annual Laravel conference featuring the latest updates and best practices.',
            'created_by' => $organizer1->id,
        ]);

        $event2 = Event::factory()->create([
            'title' => 'Music Festival Summer',
            'description' => 'Three days of amazing live music performances from top artists.',
            'created_by' => $organizer2->id,
        ]);

        $event3 = Event::factory()->create([
            'title' => 'Tech Startup Meetup',
            'description' => 'Network with fellow entrepreneurs and tech enthusiasts.',
            'created_by' => $organizer1->id,
        ]);

        Event::factory()->count(2)->create([
            'created_by' => $organizer3->id,
        ]);

        $events = Event::all();

        // Create tickets for each event
        $this->command->info('Creating tickets...');
        foreach ($events as $event) {
            // Create 2-4 different ticket types per event
            $ticketTypes = ['VIP', 'Regular', 'Early Bird', 'General Admission', 'Student'];
            $numTickets = rand(2, 4);
            $selectedTypes = array_slice($ticketTypes, 0, $numTickets);

            foreach ($selectedTypes as $type) {
                Ticket::factory()->create([
                    'event_id' => $event->id,
                    'type' => $type,
                ]);
            }
        }

        $tickets = Ticket::all();
        $this->command->info('Created ' . $tickets->count() . ' tickets');

        // Create bookings
        $this->command->info('Creating bookings...');
        $bookingCount = 0;

        foreach ($customers->take(8) as $customer) {
            // Each customer makes 1-3 bookings
            $numBookings = rand(1, 3);

            for ($i = 0; $i < $numBookings; $i++) {
                $ticket = $tickets->random();

                // Create booking
                $booking = Booking::factory()->create([
                    'user_id' => $customer->id,
                    'ticket_id' => $ticket->id,
                    'status' => rand(1, 10) > 2 ? 'confirmed' : 'pending', // 80% confirmed
                ]);

                $bookingCount++;

                // Create payment for confirmed bookings
                if ($booking->status === 'confirmed') {
                    Payment::factory()->successful()->create([
                        'booking_id' => $booking->id,
                        'amount' => $booking->total_amount,
                    ]);
                }
            }
        }

        // Create some cancelled bookings
        $cancelledCount = rand(2, 4);
        for ($i = 0; $i < $cancelledCount; $i++) {
            $customer = $customers->random();
            $ticket = $tickets->random();

            $booking = Booking::factory()->cancelled()->create([
                'user_id' => $customer->id,
                'ticket_id' => $ticket->id,
            ]);

            $bookingCount++;

            // Create refunded payment
            Payment::factory()->refunded()->create([
                'booking_id' => $booking->id,
                'amount' => $booking->total_amount,
            ]);
        }

        $this->command->info('Created ' . $bookingCount . ' bookings');

        // Summary
        $this->command->info('');
        $this->command->info('=== Seeding Complete ===');
        $this->command->info('Admins: 2');
        $this->command->info('Organizers: 3');
        $this->command->info('Customers: 10');
        $this->command->info('Events: ' . Event::count());
        $this->command->info('Tickets: ' . Ticket::count());
        $this->command->info('Bookings: ' . Booking::count());
        $this->command->info('Payments: ' . Payment::count());
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Organizer: organizer@example.com / password');
        $this->command->info('Customer: customer@example.com / password');
    }
}
