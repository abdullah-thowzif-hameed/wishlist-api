<?php

use Illuminate\Foundation\Application;

// Larastan needs the LARAVEL_VERSION constant to select the right stub files
// for the installed framework version. Its own bootstrap.php derives this by
// fully booting the Laravel app via a getcwd()-relative path to bootstrap/app.php,
// which is fragile across environments/invocation contexts. The version is a
// compile-time constant on the framework itself, so read it directly instead.
if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', Application::VERSION);
}
