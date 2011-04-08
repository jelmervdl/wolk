<?php

error_reporting(E_ALL ^ E_STRICT);
ini_set('display_errors', false);

require 'conf/timezone.php';
require 'conf/db.php';

// For some reason the Origin header is not always supplied? Maybe this hack
// will do the trick for now.
if (!isset($_SERVER['HTTP_ORIGIN']) && isset($_SERVER['HTTP_REFERER']))
	$_SERVER['HTTP_ORIGIN'] = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);