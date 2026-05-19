# Al Boukhari — Student Payments System

> Web replacement for the original Google Sheets + Apps Script system that
> sends SMS payment reminders to families of Al Boukhari School students.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+)
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CDN (no Vite — shared-hosting friendly)
- **Database**: MySQL / MariaDB
- **SMS Provider**: BulkGate (Transactional API)
- **Languages**: English (default LTR), Nederlands, العربية (RTL)

## Project Layout

```
.
├── app/                      ← The Laravel application
│   ├── app/
│   │   ├── Livewire/         ← UI components (StudentsGrid, PaymentModal, ...)
│   │   ├── Models/           ← Eloquent models
│   │   └── Services/         ← BulkGateClient, RecipientListBuilder, FeeResolver, ...
│   ├── resources/views/      ← Blade templates
│   ├── lang/                 ← Translation JSON files (en/nl/ar)
│   ├── public/assets/css/    ← Custom CSS (no build step)
│   └── routes/
├── script.js                 ← Original Google Apps Script (reference)
├── 2026_send_students_pay_re_finale.xlsx  ← Source data
├── PLAN.md                   ← Full architectural plan (Arabic)
├── FINAL_PLAN.md             ← Approved implementation plan (Arabic)
├── SUGGESTIONS.md            ← Feature inventory + decisions
├── UI_PLAN.md                ← UI design plan
└── DEPLOY.md                 ← Shared-hosting deployment guide (cPanel/Plesk)
```

## Quick Start

```bash
cd app
composer install
cp .env.example .env  # or paste your values
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Then visit http://127.0.0.1:8000 and import the xlsx file via `/import`.

## Features

- 📊 Single-page spreadsheet-like grid with all students × 12 months
- 💶 Click any month cell to open a quick payment popup (`N` for cash, `B` for bank, `Ctrl+Enter` to save & next)
- 👨‍👩‍👧 Auto-detected sibling families with one-message-per-family campaign option
- 📲 Per-student controls: hide, block messages, in-person, suspend, exclude bulk
- 🎯 5 types of SMS campaigns + dry-run preview + segment counter
- ⏰ Automatic First-Friday and Mid-month reminders
- 🌐 Full RTL/LTR with EN/NL/AR i18n
- ⚙️ Tabbed settings: General / BulkGate / WhatsApp / Reminders / Advanced

## Status

✅ Phase 1 — Foundation & student grid
✅ Phase 2 — Fees & payment modals
✅ Phase 3 — Templates, campaigns, BulkGate integration
✅ Phase 4 — Auto-reminders, reports, deployment guide

## License

Private — Al Boukhari School internal use.
