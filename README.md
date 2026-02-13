# Laravel 12 Event Booking System

A complete event booking system built with Laravel 12, featuring role-based access control, payment processing, and real-time ticket availability tracking.

## Features

### User Management
- User registration and authentication with Laravel Sanctum
- Three user roles: Admin, Organizer, and Customer
- Role-based access control with custom middleware

### Event Management
- Create, read, update, and delete events (Organizer only)
- Search and filter events by date, location, and keywords
- Paginated event listings with caching
- Soft deletes for data recovery

### Ticket Management
- Multiple ticket types per event (VIP, Regular, Early Bird, etc.)
- Real-time availability tracking
- Prevent overbooking with pessimistic locking
- Cannot modify tickets with active bookings

### Booking System
- Create bookings for available tickets
- Prevent double bookings with custom middleware
- Unique booking reference generation
- Booking status tracking (pending, confirmed, cancelled)
- Cancel bookings with automatic refund processing

### Payment Processing
- Mock payment gateway with 90% success rate
- Multiple payment methods support
- Transaction ID generation
- Payment status tracking (success, failed, refunded)
- Automatic refund processing on cancellation

### Notifications
- Queued email notifications for booking confirmations
- Database notifications for user activity tracking

## Tech Stack

- **Framework:** Laravel 12
- **Authentication:** Laravel Sanctum
- **Database:** MySQL/PostgreSQL
- **Queue:** Database driver (configure Redis for production)
- **Cache:** File driver (configure Redis for production)

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/thamjeedpbr/EventBooking.git
   cd EventBooking
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database**

   For SQLite (default):
   ```bash
   touch database/database.sqlite
   ```

   For MySQL, update `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=event_booking
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Run migrations and seeders**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Configure queue (optional but recommended)**

   Update `.env`:
   ```
   QUEUE_CONNECTION=database
   ```

   Run queue worker:
   ```bash
   php artisan queue:work
   ```

7. **Start development server**
   ```bash
   php artisan serve
   ```

8. **Access the application**
   ```
   http://localhost:8000
   ```

## Test Accounts

After seeding, use these credentials:

| Role      | Email                     | Password |
|-----------|---------------------------|----------|
| Admin     | admin@example.com         | password |
| Organizer | organizer@example.com     | password |
| Customer  | customer@example.com      | password |

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── EventController.php
│   │       ├── TicketController.php
│   │       ├── BookingController.php
│   │       └── PaymentController.php
│   ├── Middleware/
│   │   ├── CheckRole.php
│   │   └── PreventDoubleBooking.php
│   └── Requests/
│       ├── RegisterRequest.php
│       ├── LoginRequest.php
│       ├── StoreEventRequest.php
│       ├── UpdateEventRequest.php
│       ├── StoreTicketRequest.php
│       ├── UpdateTicketRequest.php
│       ├── StoreBookingRequest.php
│       └── ProcessPaymentRequest.php
├── Models/
│   ├── User.php
│   ├── Event.php
│   ├── Ticket.php
│   ├── Booking.php
│   └── Payment.php
├── Notifications/
│   └── BookingConfirmedNotification.php
├── Services/
│   ├── EventService.php
│   ├── TicketService.php
│   ├── BookingService.php
│   └── PaymentService.php
└── Traits/
    ├── ApiResponse.php
    └── CommonQueryScopes.php

database/
├── factories/
│   ├── UserFactory.php
│   ├── EventFactory.php
│   ├── TicketFactory.php
│   ├── BookingFactory.php
│   └── PaymentFactory.php
├── migrations/
│   ├── xxxx_create_users_table.php
│   ├── xxxx_modify_users_table_add_role_and_phone.php
│   ├── xxxx_create_events_table.php
│   ├── xxxx_create_tickets_table.php
│   ├── xxxx_create_bookings_table.php
│   ├── xxxx_create_payments_table.php
│   └── xxxx_create_personal_access_tokens_table.php
└── seeders/
    └── DatabaseSeeder.php

routes/
├── api.php
└── web.php
```

## API Endpoints

### Public Endpoints
- `POST /api/register` - Register new user
- `POST /api/login` - Login user
- `GET /api/events` - List all events
- `GET /api/events/{id}` - Get event details

### Protected Endpoints (require authentication)
- `POST /api/logout` - Logout user
- `GET /api/me` - Get authenticated user

### Organizer Endpoints
- `POST /api/events` - Create event
- `PUT /api/events/{id}` - Update event
- `DELETE /api/events/{id}` - Delete event
- `POST /api/tickets` - Create ticket
- `PUT /api/tickets/{id}` - Update ticket
- `DELETE /api/tickets/{id}` - Delete ticket

### Customer Endpoints
- `POST /api/bookings` - Create booking
- `GET /api/bookings` - Get user bookings
- `POST /api/bookings/{id}/cancel` - Cancel booking
- `POST /api/payments/process` - Process payment
- `GET /api/payments/{id}` - Get payment details

For detailed API documentation, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

## Key Features Implementation

### Database Transactions
All critical operations (bookings, payments, cancellations) use database transactions to ensure data consistency.

### Pessimistic Locking
Prevents race conditions when multiple users try to book the same tickets simultaneously.

### Double Booking Prevention
Custom middleware checks for existing bookings before allowing new ones.

### Caching
Event listings are cached for improved performance with automatic cache invalidation on updates.

### Validation
All user inputs are validated using Form Request classes with custom error messages.

### Error Handling
Comprehensive error handling with meaningful error messages and appropriate HTTP status codes.

### Soft Deletes
Events, tickets, bookings, and payments use soft deletes for data recovery and audit trails.

### Queued Notifications
Email notifications are queued to prevent blocking the main application flow.

## Testing

### Manual Testing
1. Use the test accounts to login
2. Test each role's permissions
3. Try creating events, tickets, and bookings
4. Process payments and verify status

### Using Postman
Import the API endpoints and test all functionality with different user roles.

### Using cURL
See examples in [API_DOCUMENTATION.md](API_DOCUMENTATION.md)

## Production Deployment

### Configuration Changes

1. **Update environment variables**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   ```

2. **Configure production database**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=your-db-host
   DB_DATABASE=your-db-name
   DB_USERNAME=your-db-user
   DB_PASSWORD=your-db-password
   ```

3. **Configure Databse for cache and queue(For development else use Redis)**
   
   ```env
   CACHE_DRIVER=database
   QUEUE_CONNECTION=database
   # REDIS_HOST=127.0.0.1
   # REDIS_PASSWORD=null
   # REDIS_PORT=6379
   ```

4. **Configure mail**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=your-mail-host
   MAIL_PORT=587
   MAIL_USERNAME=your-username
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=tls
   ```

5. **Optimize application**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Set up supervisor for queue workers (Production)**
   ```ini
   [program:event-booking-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/path/to/worker.log
   ```

## Security Considerations

- All passwords are hashed using bcrypt
- CSRF protection enabled
- SQL injection prevention through Eloquent ORM
- XSS prevention through Laravel's templating engine
- Rate limiting on API endpoints (configure as needed)
- Role-based authorization checks
- Input validation on all endpoints


---
