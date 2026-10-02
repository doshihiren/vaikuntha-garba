<?php
// ======================= SETTINGS — change only this file =======================
// Passwords are NOT in this file (the code is on GitHub). They live only on the server in data/secrets.php
// — copy data/secrets.sample.php to data/secrets.php and set your own passwords there.
if (is_file(__DIR__ . '/data/secrets.php')) require __DIR__ . '/data/secrets.php';
defined('ADMIN_PASSWORD') || define('ADMIN_PASSWORD', '');   // full access: approve / reject / WhatsApp / scan / report
defined('GATE_PASSWORD')  || define('GATE_PASSWORD', '');    // gate volunteers: QR scan only ('' = disabled)

const EVENT_NAME  = 'Vaikuntha Garba Night';
const EVENT_TAG   = 'Navratri 2026 · Chothu Nortu';
const EVENT_DATE  = '14 October 2026';
const EVENT_TIME  = '7:30 PM onwards';
const EVENT_VENUE = 'Karai Farmhouse, Vaikuntha, Karai, Gujarat 382355';
const EVENT_MAP   = 'https://www.google.com/maps/search/?api=1&query=4MJ9%2BC3W+Karai+Gujarat+382355';
const EVENT_NOTE  = 'snacks included';

const PRICE    = 500;      // ₹ per person
const MAX_PAX  = 20;       // max persons in one booking
const CAPACITY = 0;        // total persons allowed (pending + approved). 0 = no limit
const BOOKING_CLOSES = '2026-10-14 18:00';   // online booking stops at this time (IST). '' = never

const UPI_ID   = 'sunaopangm97@okicici';
const UPI_NAME = '';   // name on this UPI account (optional — shown to customers so they know whom they are paying)
const ORGANISER_WHATSAPP = '919638602133';   // with 91
// ================================================================================
