<?php

include 'lib/lightopenid/openid.php';

include 'lib/libwolk/libwolk.php';

include 'conf/db.php';

session_start();

$self = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

function index_api_login($openid_identifier)
{
	global $self;
	
	$_SESSION['user_id'] = wolk_user_id_by_openid($openid_identifier);
	header('Location: ' . $self . '?login=success');
	return;
}

function index_api_logout()
{
	global $self;
	
	unset($_SESSION['user_id']);
	header('Location: ' . $self . '?logout=success');
	return;
}

function index_login_status()
{
	global $self;
	
	$openid_identifier = wolk_user_openid($_SESSION['user_id']);
	
	$openid_host = preg_replace('/^http:\/\//', '', $openid_identifier);
	
	echo '
	<p id="login_status">
		Logged in as <a href="'.$openid_identifier.'">'.$openid_host.'</a><br>
		<a href="'.$self.'?logout=do">Log out</a>
	</p>
	';
}

function index_openid_callback()
{
	$openid = new LightOpenID();
	if($openid->validate())
		return index_api_login($_GET['openid_identity']);
	else
		return index_login();
}

function index_login()
{
	if(isset($_POST['openid_identifier'])) {
		$openid = new LightOpenID();
		$openid->identity = $_POST['openid_identifier'];
		header('HTTP/1.1 307 Temporary Redirect');
		header('Location: ' . $openid->authUrl());
		printf('Redirecting to <a href="%s">%1$s</a>…', $openid->authUrl());
		return;
	}
	
	echo '
	<form method="post" action="">
		<label for="openid_identifier">OpenID:</label>
		<input type="text" id="openid_identifier" name="openid_identifier">
		<button type="submit">Log in</button>
	</form>
	';
}

function index_list_api_keys()
{
	if(isset($_POST['generate_key'])) {
		if($key = wolk_generate_api_key($_SESSION['user_id']))
			printf('<p class="notice">A new Key has been generated: <strong>%s</strong></p>', $key->key);
	}
	
	if(isset($_POST['revoke_key'])) {
		if(wolk_revoke_api_key($_POST['revoke_key']))
			printf('<p class="notice">Key has been revoked and can no longer be used.</p>');
	}
	
	$keys = wolk_list_api_keys($_SESSION['user_id']);
	
	echo '
	<form method="post" action="">
	<ol>';
	foreach($keys as $key) {
		if($key->is_valid())
			printf('<li><strong>%s</strong> <em>added on %s</em><button type="submit" name="revoke_key" value="%1$s">Revoke Key</button></li>', 
				$key->key, $key->added_on->format('d-m-Y'));
		else
			printf('<li><del><strong>%s</strong> <em>added on %s and revoked on %s</em></li>',
				$key->key, $key->added_on->format('d-m-Y'), $key->revoked_on->format('d-m-Y'));
	}
	echo '
	</ol>
	</form>
	<form method="post" action="">
		<input type="hidden" name="generate_key" value="true">
		<button type="submit">Generate new Key</button>
	</form>
	';
}

function main()
{
	if(!isset($_SESSION['user_id'])) {
		
		if(isset($_GET['logout']) && $_GET['logout'] == 'success') {
			echo '<p class="notice">You have been logged out</p>';
		}
		
		if(!isset($_GET['openid_mode']) || $_GET['openid_mode'] == 'cancel')
			return index_login();
		else
			return index_openid_callback();
	} else {
		if(isset($_GET['logout']))
			return index_api_logout();
		
		if(isset($_GET['login']) && $_GET['login'] == 'success') {
			echo '<p class="notice">You have been logged in</p>';
		}
		
		index_login_status();
		
		index_list_api_keys();
	}
}

main();