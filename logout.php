<?php
require_once 'config.php';
session_init();
session_destroy();
header('Location: ' . APP_BASE . '/index.php');
exit;
