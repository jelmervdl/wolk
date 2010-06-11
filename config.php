<?php

error_reporting(E_ALL ^ E_STRICT);
ini_set('display_errors', true);

$db = new PDO('mysql:host=localhost;dbname=wolk', 'wolk', 'wolk');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if(isset($_GET['origin']))
	$_SERVER['HTTP_ORIGIN'] = $_GET['origin'];