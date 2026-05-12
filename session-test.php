<?php
session_start();
$_SESSION['test'] = 'working';
echo session_id();
echo '<pre>';
print_r($_SESSION);
