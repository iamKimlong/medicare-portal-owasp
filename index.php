<?php
session_start();
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

header('Location: /auth/login.php');
