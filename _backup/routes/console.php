<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('facebook:post')->everyMinute();
