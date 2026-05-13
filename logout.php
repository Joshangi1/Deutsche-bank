<?php
require_once __DIR__ . '/includes/helpers.php';
session_destroy();
header('Location: login.php');
exit;
