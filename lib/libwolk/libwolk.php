<?php

define('WOLK_EXCEPTION_MISSING_ORIGIN_HEADER', 1);
define('WOLK_EXCEPTION_MISSING_API_KEY', 2);
define('WOLK_EXCEPTION_UNKNOWN_API_KEY', 3);
define('WOLK_EXCEPTION_MISSING_POST_DATA', 4);
define('WOLK_EXCEPTION_INVALID_POST_DATA', 5);
define('WOLK_EXCEPTION_UNKNOWN_ACTION', 6);

function array_pluck($array, $index)
{
	$hits = array();
	
	foreach($array as $item) {
		if(isset($item[$index]))
			$hits[] = $item[$index];
	}
	
	return $hits;
}

function json_date($timestamp)
{
	return gmstrftime('%Y-%m-%dT%H:%M:%S.000Z', $timestamp);
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

function wolk_user_id_by_api_key($api_key)
{
	global $db;
	
	$stmt = $db->prepare("
		SELECT
			u.id
		FROM
			api_keys as a
		RIGHT JOIN users as u ON
			u.id = a.user_id
		WHERE
			a.api_key = :api_key
			AND a.revoked_on IS NULL");
	
	$stmt->bindParam(':api_key', $api_key, PDO::PARAM_STR);
	
	return $stmt->execute()
		? $stmt->fetchColumn()
		: false;
}

function wolk_user_id_by_openid($openid_identifier)
{
	global $db;
	
	$stmt = $db->prepare("SELECT id FROM users WHERE openid = :openid");
	$stmt->bindParam(':openid', $openid_identifier);
	$stmt->execute();
	if($id = $stmt->fetchColumn())
		return $id;
	
	$stmt = $db->prepare("INSERT INTO users (openid, added_on) VALUES(:openid, NOW())");
	$stmt->bindParam(':openid', $openid_identifier);
	return $stmt->execute()
		? $db->lastInsertId()
		: false;
}

function wolk_user_openid($user_id)
{
	global $db;
	
	$stmt = $db->prepare("
		SELECT
			openid
		FROM
			users
		WHERE
			id = :user_id");
	
	$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	
	return $stmt->execute()
		? $stmt->fetchColumn()
		: false;
}

function wolk_delete_user($user_id)
{
	global $db;
	
	try {
		$db->beginTransaction();
	
		$stmt = $db->prepare("UPDATE api_keys SET revoked_on = NOW() WHERE user_id = :user_id");
		$stmt->bindParam(':user_id', $user_id);
		$stmt->execute();
	
		$stmt = $db->prepare("DELETE FROM users WHERE id = :user_id");
		$stmt->bindParam(':user_id', $user_id);
		$stmt->execute();
		
		$db->commit();
		
		return $stmt->rowCount();
	} catch(PDOException $e) {
		$db->rollBack();
		throw $e;
	}
}

function wolk_list_api_keys($user_id)
{
	global $db;
	
	$stmt = $db->prepare("SELECT api_key, added_on, revoked_on FROM api_keys WHERE user_id = :user_id ORDER BY added_on ASC");
	$stmt->bindParam(':user_id', $user_id);
	$stmt->execute();
	
	return array_map(array('Wolk_ApiKey', 'fetch'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function wolk_generate_api_key($user_id)
{
	global $db;
	
	$api_key = Wolk_ApiKey::generate();
	$stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key, added_on) VALUES(:user_id, :api_key, :added_on)");
	$stmt->bindParam(':user_id', $user_id);
	$stmt->bindParam(':api_key', $api_key->key);
	$stmt->bindValue(':added_on', $api_key->added_on->format('Y-m-d H:i:s'));
	$stmt->execute();
	return $api_key;
}

function wolk_revoke_api_key($api_key)
{
	global $db;
	
	$stmt = $db->prepare("UPDATE api_keys SET revoked_on = NOW() WHERE api_key = :api_key");
	$stmt->bindParam(':api_key', $api_key);
	$stmt->execute();
	return $stmt->rowCount();
}

class Wolk_ApiKey
{
	public $key;
	
	public $added_on;
	
	public $revoked_on;
	
	public function is_valid()
	{
		return $this->revoked_on === null;
	}
	
	static public function generate()
	{
		$key = new self();
		$key->key = md5(uniqid());
		$key->added_on = new DateTime();
		return $key;
	}
	
	static public function fetch(array $data)
	{
		$key = new self();
		$key->key = $data['api_key'];
		$key->added_on = new DateTime($data['added_on']);
		$key->revoked_on = $data['revoked_on']
			? new DateTime($data['revoked_on'])
			: null;
		return $key;
	}
}

function wolk_list_origins($user_id)
{
	global $db;
	
	$stmt = $db->prepare("
		SELECT
			o.id,
			o.origin,
			MAX(p.last_modified_on) as last_updated_on
		FROM
			pairs as p
		RIGHT JOIN origins as o ON
			o.id = p.origin_id
		WHERE
			p.user_id = :user_id
		GROUP BY
			o.id,
			o.origin
	");
	
	$stmt->bindParam(':user_id', $user_id);
	$stmt->execute();
	
	return array_map(array('Wolk_Origin', 'fetch'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

class Wolk_Origin
{
	public $id;
	
	public $origin;
	
	public $last_updated_on;
	
	static public function fetch(array $data)
	{
		$origin = new self();
		$origin->id = (int) $data['id'];
		$origin->origin = $data['origin'];
		$origin->last_updated_on = new DateTime($data['last_updated_on']);
		return $origin;
	}
}

function wolk_list_pairs($user_id, $origin_id, array $namespaces = null, $since = null)
{
	global $db;
	
	$conditions = array(
		array('origin_id = :origin_id', ':origin_id' => $origin_id),
		array('user_id = :user_id', ':user_id' => $user_id)
	);
	
	if($namespaces && count($namespaces)) {
		foreach($namespaces as $namespace)
			$namespace_conditions[] = 'pair_key LIKE ' . $db->quote($namespace . '.%');
	
		$conditions[] = array('(' . implode(' OR ', $namespace_conditions) . ')');
	}
	
	if($since) {
		$datetime = date('Y-m-d H:i:s', strtotime($since));
		$conditions[] = array('last_modified_on > :since', ':since' => $datetime);
	}
	
	$stmt = $db->prepare("
		SELECT
			pair_key,
			pair_value,
			last_modified_on
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

	return array_map(array('Wolk_Pair', 'fetch'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

class Wolk_Pair
{
	public $key;
	
	public $value;
	
	public $last_modified_on;
	
	static public function fetch(array $data)
	{
		$pair = new self();
		$pair->key = $data['pair_key'];
		$pair->value = $data['pair_value'];
		$pair->last_modified_on = new DateTime($data['last_modified_on']);
		return $pair;
	}
}

function wolk_api_user()
{
	if(!isset($_GET['api_key']))
		throw new Wolk_API_Exception('Missing api_key parameter', WOLK_EXCEPTION_MISSING_API_KEY);
	
	if(!($user_id = wolk_user_id_by_api_key($_GET['api_key'])))
		throw new Wolk_API_Exception('Unkown api key', WOLK_EXCEPTION_UNKNOWN_API_KEY);
	
	return (int) $user_id;
}

function wolk_api_read($user_id, $origin_id, array $namespaces = null, $since = null)
{
	$pairs = wolk_list_pairs($user_id, $origin_id, $namespaces, $since);
	
	$response = array();
	
	foreach($pairs as $pair) {
		$response[] = array(
			'k' => $pair->key,
			'v' => $pair->value,
			'm' => json_date($pair->last_modified_on->getTimestamp())
		);
	}
	
	return $response;
}

function wolk_api_write($user_id, $origin_id, array $data)
{
	global $db;
	
	$stmt = $db->prepare("
		INSERT INTO pairs
			(pair_key, pair_value, last_modified_on, origin_id, user_id)
			VALUES (:key, :value, :last_modified_on, :origin_id, :user_id)
		ON DUPLICATE KEY UPDATE
			pair_value = IF(VALUES(last_modified_on) > last_modified_on, VALUES(pair_value), pair_value),
			last_modified_on = IF(VALUES(last_modified_on) > last_modified_on, VALUES(last_modified_on), last_modified_on)");
	
	$stmt->bindParam(':origin_id', $origin_id, PDO::PARAM_INT);
	$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	
	$stmt->bindParam(':key', $key, PDO::PARAM_STR);
	$stmt->bindParam(':value', $value, PDO::PARAM_STR);
	$stmt->bindParam(':last_modified_on', $last_modified_on, PDO::PARAM_STR);
	
	$n = 0;
	
	foreach($data as $pair) {
		$key = $pair->k;
		$value = $pair->v;
		$last_modified_on = wolk_api_date_to_sql($pair->m);
		$stmt->execute();
		$n += ($stmt->rowCount() > 0); // because it's always 0 or 2? Weird.
	}
	
	return $n;
}

function wolk_api_date_to_sql($json_date)
{
	$result = date('Y-m-d H:i:s', strtotime($json_date));
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
	}
	catch(Exception $e) {
		header("HTTP/1.0 500 Internal Server Error");
		echo '<pre>' . $e . '</pre>';
	}
}