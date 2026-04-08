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
