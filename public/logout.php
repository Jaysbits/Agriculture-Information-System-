<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

if (is_post() && Auth::check()) {
    verify_csrf();
    Auth::logout();
    session_start();
    flash('success', 'You have been signed out.');
    redirect('login.php');
}

redirect(Auth::check() ? 'index.php' : 'login.php');









