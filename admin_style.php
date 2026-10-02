<?php if (!function_exists("h")) exit; ?>
<style>
:root{--bg:#FBF3E4;--card:#fff;--fg:#3A1A12;--muted:#7A5A4C;--accent:#9E1B32;--line:#EADBC2;--ok:#1E7A4A;--okbg:#D8F0E2;--warn:#9A5B00;--warnbg:#FDEBC8;--bad:#B3261E;--badbg:#F9DEDC;--wa:#1E8E4E}
.btn:disabled{opacity:.5}
[hidden]{display:none!important}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--fg);font-size:15px}
header{position:sticky;top:0;z-index:5;background:var(--accent);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
header b{flex:1;font-size:1rem;min-width:140px}header a{color:#fff;text-decoration:none;font-weight:600;padding:7px 11px;border-radius:8px;background:#ffffff22;font-size:.9rem}
header a.on{background:#fff;color:var(--accent)}
main{max-width:900px;margin:0 auto;padding:16px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px}
.stat{background:var(--card);border-radius:12px;padding:10px 12px;border:1px solid var(--line)}.stat small{display:block;color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em}
.stat b{font-size:1.35rem;font-variant-numeric:tabular-nums}
.btn{font:inherit;font-weight:700;border:0;border-radius:10px;padding:10px 14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.btn.p{background:var(--accent);color:#fff}.btn.wa{background:var(--wa);color:#fff}.btn.g{background:var(--card);color:var(--fg);border:1px solid var(--line)}
.muted{color:var(--muted);font-size:.85rem}
</style>
