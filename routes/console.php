<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('chatbot:timeouts')->everyMinute()->withoutOverlapping();
Schedule::command('connections:sync')->everyMinute()->withoutOverlapping();
Schedule::command('whatsapp:reconcile')->dailyAt('00:00')->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
