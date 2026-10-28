<?php

use Illuminate\Support\Facades\Schedule;

// Tandai penawaran yang melewati masa berlaku.
Schedule::command('marketing:expire-quotations')->dailyAt('01:00');
