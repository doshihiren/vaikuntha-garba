# Vaikuntha Garba Night — booking & entry system

Live: https://awelion.com/vaikuntha-garba

PHP + SQLite (no framework). Runs on Hostinger shared hosting.

## Flow
1. **Customer** (`index.php`) fills name / mobile / email / persons → pays by UPI QR / GPay → enters 12-digit UTR → booking saved as **Pending** (`save.php`). Can check status any time (`status.php`).
2. **Organiser** (`admin.php`) matches UTR in bank → **Approve / Pending / Reject** → **① Message** (WhatsApp text with event details + ticket link) → **② Ticket image** (PNG ticket with QR, shared to WhatsApp via the phone's share sheet).
3. **Gate** (`scan.php`) scans the QR → green ENTRY OK once, red ALREADY USED after that (atomic, safe with several gate phones).
4. **Report** (`report.php`) summary, lists, Excel export, activity log, database backup.

## Files
| File | Purpose |
|---|---|
| `config.php` | Event details, price, UPI ID, capacity, closing time |
| `data/secrets.php` | Passwords — **server only**, create from `data/secrets.sample.php` |
| `data/.ht-garba.sqlite` | Database — **server only**, created automatically, never committed |
| `lib.php` | DB, helpers, login |
| `api.php` | Staff actions (status, WhatsApp, check-in) |

## Deploy
Upload/pull the files into `public_html/vaikuntha-garba`. **Do not delete the `data/` folder on the server** — it holds the live database and passwords.
Requirements: PHP 8+, `pdo_sqlite` extension, HTTPS (camera scanning needs it).
