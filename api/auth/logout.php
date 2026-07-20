<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();
require_method('POST','DELETE');
session_start();
session_destroy();
setcookie(session_name(),'',time()-3600,'/','',isset($_SERVER['HTTPS']),true);
json_ok(['message'=>'Logged out']);
