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
	header('Location: ' . $self . '?message=' . urlencode('You have been logged in'));
	return;
}

function index_api_logout($message = 'You have been logged out')
{
	global $self;
	
	unset($_SESSION['user_id']);
	header('Location: ' . $self . '?message=' . urlencode($message));
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

function index_api_keys()
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
	
	if(count($keys) > 0) {
		echo '
		<form method="post" action="">
		<ol>
		';
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
		';
	}
	else {
		echo '<p>You have not API keys yet. Generate one now!</p>';
	}
	
	echo '
	<form method="post" action="">
		<input type="hidden" name="generate_key" value="true">
		<button type="submit">Generate new Key</button>
	</form>
	';
}

function index_navigation()
{
	global $self;
	
	echo '
	<ul>
		<li><a href="'.$self.'?page=account">Account</a></li>
		<li><a href="'.$self.'?page=data">Data</a></li>
		<li><a href="'.$self.'?page=keys">Keys</a></li>
	</ul>
	';
}

function index_account()
{
	if(isset($_POST['delete_account']) && $_POST['delete_account'] == 'on') {
		if(!isset($_POST['delete_account_token']) || $_POST['delete_account_token'] != $_SESSION['delete_account_token']) {
			echo '<p class="error">Invalid delete-account-token. Please try again</p>';
		}
		else if(wolk_delete_user($_SESSION['user_id'])) {
			return index_api_logout('Your account has been deleted and data erased');
		}
		else {
			echo '<p class="error">Account could not be deleted or already has been deleted?</p>';
		}
	}
	
	$_SESSION['delete_account_token'] = uniqid();
	
	echo '
	<form method="post" action="">
		<input type="hidden" name="delete_account_token" value="'.$_SESSION['delete_account_token'].'">
		<input type="checkbox" name="delete_account" id="delete_account" value="on">
		<label for="delete_account">Delete by all my data from the database and anonymize &amp; revoke all my API keys.</label>. Note: the data in your browser won\'t be affected.
		<br>
		<button type="submit">Delete my account</button>
	</form>
	';
}

function index_data()
{
	global $self;
	
	$selected_origin = isset($_GET['origin'])
		? (int) $_GET['origin']
		: null;
	
	echo '
	<ol>
	';
	foreach(wolk_list_origins($_SESSION['user_id']) as $origin) {
		echo '
		<li class="'.($origin->id == $selected_origin ? 'selected' : '').'">
			<a href="'.$self.'?page=data&origin='.urlencode($origin->id).'">' . htmlspecialchars($origin->origin, ENT_COMPAT, 'utf-8') . '</a>
			<span class="last_updated timestamp">Last updated on ' . $origin->last_updated_on->format('d-m-Y H:i:s') . '</span>
		</li>
		';
	}
	echo '
	</ol>
	';
	
	if($selected_origin) {
		echo '
		<table>
			<thead>
				<tr>
					<th>Key</th>
					<th>Value</th>
					<th>Last Updated</th>
				</tr>
			</thead>
			<tbody>
		';
		foreach(wolk_list_pairs($_SESSION['user_id'], $selected_origin) as $pair) {
			echo '
				<tr>
					<td>'.htmlspecialchars($pair->key, ENT_COMPAT, 'utf-8').'</td>
					<td>'.htmlspecialchars($pair->value, ENT_COMPAT, 'utf-8').'</td>
					<td>'.$pair->last_modified_on->format('d-m-Y H:i:s').'</td>
				</tr>
			';
		}
		echo '
			</tbody>
		</table>
		';
	}
}

function main()
{
	ob_start();
	
	if(isset($_GET['message'])) {
		echo '<p class="notice">'.htmlspecialchars($_GET['message'], ENT_COMPAT, 'utf-8').'</p>';
	}
	
	if(!isset($_SESSION['user_id'])) {
		
		if(!isset($_GET['openid_mode']) || $_GET['openid_mode'] == 'cancel')
			return index_login();
		else
			return index_openid_callback();
	} else {
		if(isset($_GET['logout']))
			return index_api_logout();
		
		index_login_status();
		
		index_navigation();
		
		switch(isset($_GET['page']) ? $_GET['page'] : null) {
			case 'account':
				index_account();
				break;
			case 'data':
				index_data();
				break;
			case 'keys':
			default:
				index_api_keys();
				break;
		}
	}
}

main();