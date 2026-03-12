# HealthPro (Hp)

A clinic reception and practice management application built with Laravel 12 and Livewire 4. It handles walk-in and appointment visits, queues, invoicing, shift management, and doctor payouts with optional receipt printing.

## Features

- **Shift management** — Open/close reception shifts with opening cash; track expected vs actual cash; record expenses and print shift-close receipts.
- **Walk-in** — Register patients by family phone, add visits with services (and doctor/service pricing), issue queue tokens, create invoices, and print receipts.
- **Appointments** — Reserve appointment slots per doctor (service id 1), lookup by phone, reserve tokens for a time slot, then confirm and complete like walk-in (invoice, pay, print).
- **Queues** — View active and past queues; see tokens (reserved, waiting, called, completed). Queues can be continuous, daily, or shift-based.
- **Invoices** — List/filter invoices (by shift or date range), view details, and print invoice receipts.
- **Doctor payout** — For doctors not on payroll (`is_on_payroll = false`) with a `payout_duration` (days): see unpaid invoice lines (doctor share), pay out in bulk, and print payout receipts.
- **CRUDs** — Manage **Doctors** (name, specialization, phone, payroll flag, payout duration, status), **Services** (name, standalone flag), and **Service prices** (per service/doctor: price, doctor_share, hospital_share).
- **Auth & settings** — Laravel Fortify: login, registration, email verification, password reset, profile, password change, appearance, two-factor auth.

## How It Works

### Data model (summary)

- **Family** — Identified by `phone`; has many **Patient**s (name, gender, dob, relation_to_head).
- **Visit** — Belongs to a patient and (optionally) a shift; status: reserved, confirmed, completed, cancelled. Has many **VisitService**s (service + doctor) and **QueueToken**s; has one **Invoice**.
- **Invoice** — Tied to a visit and patient; has many **InvoiceService**s (linked to **ServicePrice**). Status: unpaid, paid, partial paid.
- **Queue** — Per service and doctor; has type (continuous, daily, shift), current_token, status (active/discontinued), started_at/ended_at. Has many **QueueToken**s (token_number, status: reserved, waiting, called, completed, skipped, cancelled).
- **Shift** — opened_at, opening_cash, closed_at, cash_in_hand; has visits, invoices, queue_tokens, expenses, doctor_payouts.
- **Doctor** — name, specialization, phone, is_on_payroll, payout_duration (days), status. **DoctorPayout** and **DoctorPayoutLedger** track payments against invoice services.

Reception workflows (walk-in and appointment) create visits, visit_services, queue_tokens, and invoices within the current shift. Doctor payouts use **DoctorPayoutLedger** so each invoice service line is only paid once.

### Scheduled task

- **Daily at 00:01** — `queues:close-daily-appointment` runs to close still-open queues for the “appointment” service (service id 1) and doctor id > 1, so tokens renew daily. Ensure the scheduler is running (e.g. cron calling `php artisan schedule:work` or `schedule:run`).

### Receipt printing

The app uses `mike42/escpos-php` and custom templates under `App\Printing\ReceiptTemplates` (e.g. invoice, shift close, doctor payout). Receipt actions (e.g. `App\Actions\PrintReceipt`) send output to a configured printer; configure your printer/connection in the app as needed.

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm (for Vite/frontend)
- SQLite (default) or MySQL/PostgreSQL (set in `.env`)
- Optional: queue worker and cron for scheduler (see below)

## Installation

1. **Clone and install PHP dependencies**

   ```bash
   git clone <repository-url>
   cd Hp
   composer install
   ```

2. **Environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Edit `.env`: set `APP_NAME`, `APP_URL`, and database (default is SQLite; create `database/database.sqlite` if using SQLite).

3. **Database**

   ```bash
   php artisan migrate
   ```

   Optionally seed data:

   ```bash
   php artisan db:seed
   ```

4. **Frontend**

   ```bash
   npm install
   npm run build
   ```

   For development with hot reload:

   ```bash
   npm run dev
   ```

5. **Queue & scheduler (recommended for production)**

   - Run queue worker: `php artisan queue:work` (or `queue:listen`) so queued jobs run.
   - Run scheduler: add to crontab `* * * * * cd /path-to-Hp && php artisan schedule:run >> /dev/null 2>&1` or use `php artisan schedule:work` in development.

## Usage

### Running the application

- **Web server**  
  If using Laravel Herd, the app is served at `https://hp.test` (or the configured Herd URL).  
  Otherwise: `php artisan serve` (e.g. http://localhost:8000).

- **Full dev stack** (server + queue + Vite):

  ```bash
  composer run dev
  ```

  This runs the app, queue listener, and Vite dev server.

### After login

1. **Open a shift** — Go to **Reception → Shift**. Open a shift with opening cash. Only one shift can be open at a time.
2. **Walk-in** — **Reception → Walk-in**. Enter family phone to load patients; select patient, add services (and doctors/prices), get queue tokens, create invoice, pay, print receipt.
3. **Appointments** — **Reception → Appointments**. Select doctor, enter phone, select patient (or add new), choose time slot, reserve token. Then confirm visit, add services if needed, create invoice, pay, print.
4. **Queues** — **Reception → Queues**. Switch between active and older queues; select a queue to see its tokens and call/complete as needed.
5. **Invoices** — **Reception → Invoices**. Filter by current shift or date range, search; open an invoice to print receipt.
6. **Doctor payout** — **Reception → Doctor payout**. Select a doctor (with payout_duration set, not on payroll). Review unpaid lines and total share; run payout, then print payout receipt.
7. **CRUDs** — **Cruds**: Doctors, Services, and Pricing (service prices per doctor). Configure doctors’ payout duration and payroll flag here.
8. **Settings** — Profile, password, appearance, two-factor (from user menu).

### Creating a user

- Register via the auth flow, or create a user in the database and set a password (e.g. with `php artisan tinker` or a seeder).

## Testing

```bash
php artisan test --compact
```

To run a subset of tests:

```bash
php artisan test --compact --filter=ShiftTest
php artisan test --compact --filter=ReceptionWalkin
```

## Code style

```bash
vendor/bin/pint --dirty
```

## Tech stack

- **Backend:** Laravel 12, Laravel Fortify
- **Frontend:** Livewire 4, Flux UI (free), Tailwind CSS v4, Vite
- **Printing:** mike42/escpos-php
- **Testing:** Pest 4

## License

MIT (or as specified in the project).
