<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rakhi:checkin-reminder')
    ->dailyAt('08:00')
    ->timezone('Asia/Kolkata');

Schedule::command('rakhi:streak-check')
    ->dailyAt('00:05')
    ->timezone('Asia/Kolkata');

Schedule::command('rakhi:subscription-check')
    ->dailyAt('00:10')
    ->timezone('Asia/Kolkata');

// Proactive reminders — runs every 30 min, service handles time-window logic internally
Schedule::command('rakhi:proactive-followup')
    ->everyThirtyMinutes()
    ->timezone('Asia/Kolkata');

// Recover users stuck in plan generating state with no active queue job
Schedule::command('rakhi:recover-stuck-plans')
    ->everyFifteenMinutes()
    ->timezone('Asia/Kolkata');
