<?php
session_start();
require_once __DIR__ . '/../config.php';

writeAuditLog('logout');
session_destroy();
header('Location: /auth/login.php');
