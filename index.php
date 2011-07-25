<?php

require 'lib/lightopenid/openid.php';
require 'lib/libwolk/libwolk.php';

include 'conf/website.php';

session_start();

$self = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

$pages = array(
	'keys' => 'Keys',
	'events' => 'Events',
	'data' => 'Data',
	'account' => 'Account'
);

$current_page = isset($_GET['page']) && isset($pages[$_GET['page']])
	? $_GET['page']
	: 'keys';

function _relative_date($datetime)
{
	$diff = time() - $datetime->getTimestamp();

	$steps = array(
		array(1,			array('second', 'seconds')),
		array(60,			array('minute', 'minutes')),
		array(3600,			array('hour', 'hours')),
		array(24*3600,		array('day', 'days')),
		array(7*24*3600,	array('week', 'weeks')),
		array(30*24*3600,	false)
	);

	for($i = 1; $i < count($steps); ++$i) {
		if($diff >= $steps[$i][0])
			continue;
	
		$scaled = round($diff / $steps[$i-1][0]);
	
		return sprintf('%d %s ago', $scaled, $scaled === 1.0 ?
			$steps[$i - 1][1][0] : $steps[$i - 1][1][1]);
	}

	return $datetime->format('j F, Y');
}

function index_api_login($openid_identifier)
{
	global $self;
	
	$_SESSION['user_id'] = wolk_user_id_by_openid($openid_identifier);
	header('Location: ' . $self . '?message=' . urlencode('You have been logged in'));
	ob_end_clean();
	exit;
}

function index_api_logout($message = 'You have been logged out')
{
	global $self;
	
	unset($_SESSION['user_id']);
	header('Location: ' . $self . '?message=' . urlencode($message));
	ob_end_clean();
	exit;
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

function _openid_discover_email($email, &$error = null)
{
	if(preg_match('/^[a-z0-9\!#\$%&\'\*\+\-\/\=\?\^_`\{\|\}~\.]+@([a-z0-9\.\-]+)$/i', $email, $matches)
		&& ($dns_records = dns_get_record($matches[1], DNS_MX))
		&& isset($dns_records[0])
		&& preg_match('/\.googlemail\.com$|\.google\.com$/i', $dns_records[0]['target']))
		return 'https://www.google.com/accounts/o8/id';
	else
		return $email;
}

function index_login()
{
	if(isset($_POST['openid_identifier'])) {
		$openid = new LightOpenID();
		$openid->identity = _openid_discover_email($_POST['openid_identifier']);
		$auth_url = $openid->authUrl(true);
		ob_end_clean();
		header('HTTP/1.1 307 Temporary Redirect');
		header('Location: ' . $auth_url);
		printf('Redirecting to <a href="%s">%1$s</a>…', $auth_url);
		exit;
	}
	
	echo '
	<form method="post" action="">
		<label for="openid_identifier">OpenID or Google ID:</label>
		<input type="text" id="openid_identifier" name="openid_identifier">
		<button type="submit">Sign in</button>
	</form>
	';
}

function index_navigation()
{
	global $self, $pages, $current_page;
	
	echo '<ul>';
	foreach($pages as $page => $name)
		echo '<li class="'.($page == $current_page ? 'selected' : '').'"><a href="'.$self.'?page='.$page.'">'.$name.'</a></li>';
	echo '</ul>';
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
				printf('<li><strong>%s</strong> <em>added on <time timestamp="%s">%s</time></em><button type="submit" name="revoke_key" value="%1$s">Revoke Key</button></li>', 
					$key->key, json_stringify_date($key->added_on), $key->added_on->format('d-m-Y'));
			else
				printf('<li><del><strong>%s</strong> <em>added on <time datetime="%s">%s</time> and revoked on <time datetime="%s">%s</time></em></li>',
					$key->key,
					json_stringify_date($key->added_on), $key->added_on->format('d-m-Y'),
					json_stringify_date($key->revoked_on), $key->revoked_on->format('d-m-Y'));
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

function index_events()
{
	echo "\t<ol>\n";
	foreach(wolk_list_events($_SESSION['user_id']) as $event) {
		printf("\t\t<li>%s%s <time datetime=\"%s\">%s</time></li>\n",
			htmlspecialchars($event->message, ENT_COMPAT, 'utf-8'),
			$event->api_key ? ' using <em>' . $event->api_key . '</em>' : '',
			json_stringify_date($event->created_on),
			_relative_date($event->created_on));
	}
	echo "\t</ol>\n";
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
	
	if ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		switch ($_POST['action'])
		{
			case 'delete':
				wolk_delete_pairs($_SESSION['user_id'], $_POST['keys'], $selected_origin);
				break;
		}
	}
	
	echo '
	<ol>
	';
	foreach(wolk_list_origins($_SESSION['user_id']) as $origin) {
		echo '
		<li class="'.($origin->id == $selected_origin ? 'selected' : '').'">
			<a href="'.$self.'?page=data&origin='.urlencode($origin->id).'">' . htmlspecialchars($origin->origin, ENT_COMPAT, 'utf-8') . '</a>
			<span class="last_updated timestamp">Last updated on <time datetime="' . json_stringify_date($origin->last_updated_on) . '">' . $origin->last_updated_on->format('d-m-Y H:i:s') . '</time></span>
		</li>
		';
	}
	echo '
	</ol>
	';
	
	if($selected_origin) {
		echo '
		<form method="post">
			<table>
				<thead>
					<tr>
						<th><input type="checkbox"></th>
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
						<td><input type="checkbox" name="keys[]" value="'.htmlspecialchars($pair->key, ENT_QUOTES, 'utf-8').'"></td>
						<td>'.htmlspecialchars($pair->key, ENT_COMPAT, 'utf-8').'</td>
						<td>'.(is_null($pair->value) ? '<span class="deleted">deleted</span>' : htmlspecialchars($pair->value, ENT_COMPAT, 'utf-8')).'</td>
						<td><time timestamp="'.json_stringify_date($pair->last_modified_on).'">'.$pair->last_modified_on->format('d-m-Y H:i:s').'</time></td>
					</tr>
			';
		}
		echo '
				</tbody>
			</table>
			<button type="submit" name="action" value="delete">Delete</button>
		</form>
		';
	}
}

function index_header()
{
	global $pages, $current_page;
	
	echo '<!DOCTYPE>
<html>
	<head>
		<title>'.$pages[$current_page].' – Wolk</title>
		<style>
			.selected {
				color: red;
			}
			.deleted {
				color: red;
				font-style: italic;
			}
		</style>
	</head>
	<body>
	';
}

function index_footer()
{
	echo '
	</body>
</html>';
}

function main()
{
	global $current_page;
	
	ob_start();
	
	index_header();
	
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
		
		switch($current_page) {
			case 'account':
				index_account();
				break;
			case 'events':
				index_events();
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
	
	index_footer();
}

main();