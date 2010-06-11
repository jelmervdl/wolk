<?php

define('WOLK_EXCEPTION_MISSING_ORIGIN_HEADER', 1);
define('WOLK_EXCEPTION_MISSING_API_KEY', 2);
define('WOLK_EXCEPTION_UNKNOWN_API_KEY', 3);
define('WOLK_EXCEPTION_MISSING_POST_DATA', 4);
define('WOLK_EXCEPTION_INVALID_POST_DATA', 5);
define('WOLK_EXCEPTION_UNKNOWN_ACTION', 6);

function _debug($msg) {
	file_put_contents('log.txt', $msg . "\n", FILE_APPEND);
}

function array_pluck($array, $index)
{
	$hits = array();
	
	foreach($array as $item) {
		if(isset($item[$index]))
			$hits[] = $item[$index];
	}
	
	return $hits;
}

function wolk_origin_id($origin)
{
	global $db;
	
	$stmt = $db->prepare("SELECT id FROM origins WHERE origin = :origin");
	$stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
	return $stmt->execute()
		? $stmt->fetchColumn()
		: false;
}

function wolk_origin_add($origin)
{
	global $db;
	
	$stmt = $db->prepare("INSERT INTO origins (origin) VALUES(:origin)");
	$stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
	return $stmt->execute()
		? $db->lastInsertId()
		: false;
}

function wolk_api_send_accept_headers()
{
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: POST, GET');
	header('Access-Control-Allow-Headers: Content-Type, Origin');
	//header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 1728000');
}

function wolk_api_origin()
{
	if(!isset($_SERVER['HTTP_ORIGIN']))
		throw new Wolk_API_Exception('Missing Origin header', WOLK_EXCEPTION_MISSING_ORIGIN_HEADER);
	
	$origin_id = wolk_origin_id($_SERVER['HTTP_ORIGIN']);
	
	if(!$origin_id)
		$origin_id = wolk_origin_add($_SERVER['HTTP_ORIGIN']);
	
	return (int) $origin_id;
}

function wolk_user_id($api_key)
{
	global $db;
	
	$stmt = $db->prepare("SELECT id FROM users WHERE api_key = :api_key");
	$stmt->bindParam(':api_key', $api_key, PDO::PARAM_STR);
	return $stmt->execute()
		? $stmt->fetchColumn()
		: false;
}

function wolk_api_user()
{
	if(!isset($_GET['api_key']))
		throw new Wolk_API_Exception('Missing api_key parameter', WOLK_EXCEPTION_MISSING_API_KEY);
	
	if(!($user_id = wolk_user_id($_GET['api_key'])))
		throw new Wolk_API_Exception('Unkown api key', WOLK_EXCEPTION_UNKNOWN_API_KEY);
	
	return (int) $user_id;
}

function wolk_api_read($user_id, $origin_id, array $namespaces = null, $since = null)
{
	global $db;
	
	$conditions = array();
	
	$conditions[] = array('origin_id = :origin_id', ':origin_id' => $origin_id);
	
	$conditions[] = array('user_id = :user_id', ':user_id' => $user_id);
	
	if($namespaces && count($namespaces)) {
		foreach($namespaces as $namespace)
			$namespace_conditions[] = 'pair_key LIKE ' . $db->quote($namespace . '.%');
	
		$conditions[] = array('(' . implode(' OR ', $namespace_conditions) . ')');
	}
	
	if($since) {
		$datetime = date('Y-m-d H:i:s', strtotime($since));
		$conditions[] = array('mtime > :since', ':since' => $datetime);
	}
	
	$stmt = $db->prepare("
		SELECT
			pair_key as k,
			pair_value as v,
			mtime as m
		FROM
			pairs
		WHERE " . implode(' AND ', array_pluck($conditions, 0)));
	
	foreach($conditions as $condition) {
		foreach($condition as $placeholder => $value) {
			if(!is_int($placeholder))
				$stmt->bindValue($placeholder, $value);
		}
	}
	
	$stmt->execute();

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function wolk_api_write($user_id, $origin_id, array $data)
{
	global $db;
	
	$stmt = $db->prepare("INSERT INTO pairs (pair_key, pair_value, mtime, origin_id, user_id)
		VALUES (:key, :value, :mtime, :origin_id, :user_id)
		ON DUPLICATE KEY UPDATE pair_value = VALUES(pair_value), mtime = VALUES(mtime)");
	
	$stmt->bindParam(':origin_id', $origin_id, PDO::PARAM_INT);
	$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	
	$stmt->bindParam(':key', $key, PDO::PARAM_STR);
	$stmt->bindParam(':value', $value, PDO::PARAM_STR);
	$stmt->bindParam(':mtime', $mtime, PDO::PARAM_STR);
	
	$n = 0;
	
	foreach($data as $pair) {
		$key = $pair->k;
		$value = $pair->v;
		$mtime = wolk_api_date_to_sql($pair->m);
		$stmt->execute();
		++$n;
	}
	
	return $n;
}

function wolk_api_date_to_sql($json_date)
{
	$result = date('Y-m-d H:i:s', strtotime($json_date));
	_debug('in: ' . $json_date . ' // out: ' . $result);
	return $result;
}

class Wolk_API_Exception extends Exception {}

class Wolk_API_AuthException extends Wolk_API_Exception {}

function wolk_api_main($action)
{
	try {
		
		$response = null;
		
		wolk_api_send_accept_headers();
		
		switch($action)
		{
			case 'OPTIONS':
				break;
			
			case 'GET':
				$response = wolk_api_read(
					wolk_api_user(),
					wolk_api_origin(),
					isset($_GET['namespaces']) && is_array($_GET['namespaces'])
						? $_GET['namespaces']
						: null,
					isset($_GET['since'])
						? $_GET['since']
						: null
				);
				break;
			
			case 'POST':
				$user_id = wolk_api_user();
				
				$origin_id = wolk_api_origin();
				
				if(!($raw = file_get_contents('php://input')))
					throw new Wolk_API_Exception('Cannot read POST data', WOLK_EXCEPTION_MISSING_POST_DATA);
				
				if(!($data = json_decode($raw)))
					throw new Wolk_API_Exception('Cannot decode JSON post data', WOLK_EXCEPTION_INVALID_POST_DATA);
			
				if(!is_array($data))
					throw new Wolk_API_Exception('Expected an array as post data', WOLK_EXCEPTION_INVALID_POST_DATA);
			
				$response = wolk_api_write($user_id, $origin_id, $data);
				break;
			
			default:
				throw new Wolk_API_Exception('Unknown action', WOLK_EXCEPTION_UNKNOWN_ACTION);
		}
		
		header("HTTP/1.0 200 OK");
		
		if($response !== null) {
			header('Content-Type: application/json');
			echo json_encode($response);
		}
	}
	catch(Wolk_API_Exception $e) {
		header("HTTP/1.0 500 Internal Server Error");
		header('X-Error-Code: ' . $e->getCode());
		echo $e->getMessage();
		_debug($e);
	}
	catch(Exception $e) {
		header("HTTP/1.0 500 Internal Server Error");
		echo '<pre>' . $e . '</pre>';
		_debug($e);
	}
}