<?php

// Showcase prep, Phase 3 (§7.33) — the light `/showcase` access gate's
// own config, deliberately separate from config/auth.php/anything the
// real Dashboard User system reads. `passcode` empty/null (the default —
// no SHOWCASE_PASSCODE in .env) disables the gate entirely: the same
// "safe default for local dev, opt into stricter behavior explicitly"
// shape CACHE_STORE=database/WOOCOMMERCE_*/PLANNER_TYPE=deterministic
// already establish throughout this codebase — never require a secret to
// exist just to run the demo locally.
return [
    'passcode' => env('SHOWCASE_PASSCODE'),
];
