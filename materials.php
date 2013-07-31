<?php
// global config
/* cookie jar,need writable */
$cookie_jar = '/tmp';
/* set your admin username and password */
$admin_user = '';
$admin_pass = '';
/* database setting */
$db_host = 'localhost';
$db_user = '';
$db_pass = '';
$db_name = '';

// global variables
/* session auth token */
$token = '';
/* browser */
$ch = get_agent();
/* database */
$db = get_database($db_host,$db_user,$db_pass,$db_name);

// processing
$logined_url = get_login($admin_user,$admin_pass);

$materials_url = get_logined($logined_url);

get_materials($materials_url);

curl_close($ch);
	
function get_agent() {
	global $cookie_jar;
	echo "start...<br />\n";

	if(! extension_loaded('curl')) {
		echo "Fatal : couldn't found curl extension in PHP! Please enable it.<br />\n";
		exit;
	}
	$user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FAILONERROR, true); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	if (! is_dir($cookie_jar) || ! is_writable($cookie_jar)) {
		$cookie_jar = ini_get('upload_tmp_dir');
	}
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar . '/weixin_admin.cookie');

	return $ch;
}
function get_database($db_host,$db_user,$db_pass,$db_name)
{
	$db = null;
	if (! empty($db_host) && ! empty($db_user) && ! empty($db_name)) {
		$db = mysql_connect($db_host,$db_user,$db_pass);
		mysql_select_db($db_name);
		mysql_query("SET NAMES UTF8");
	} else {
		echo "Not found database setting, don't use database support.<br />\n";
	}

	return $db;
}
function get_login($admin_user,$admin_pass) {
	global $ch,$token;
	$refer_url = 'http://admin.wechat.com/cgi-bin/loginpage?t=wxm2-login&lang=en_US';
	$login_url = 'http://admin.wechat.com/cgi-bin/login?lang=en_US';

	if ($admin_user === '' || $admin_pass === '') {
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$admin_user = $_SERVER['PHP_AUTH_USER'];
			$admin_pass = $_SERVER['PHP_AUTH_PW'];
		} else {
			header('WWW-Authenticate: Basic realm="Weixin MP Authenticate"');
		    header('HTTP/1.0 401 Unauthorized');
		    echo "Please enter your Weixin MP's account and password.";
		    exit;
		}
	}
	echo "do login...\n";

	$post_data = array(
		'username' 	=> $admin_user,
		'pwd'		=> md5(substr($admin_pass,0,16)),
		'imgcode'   => '',
		'f'         => 'json');
	curl_setopt($ch, CURLOPT_URL, $login_url);
	curl_setopt($ch, CURLOPT_REFERER, $refer_url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$output = curl_redir_exec($ch,0);
	if ($output === false) {
		echo curl_error($ch);
		exit;
	}

	$logined = json_decode($output);
	$logined_url = '';
	$error_msg = '';
	switch ($logined->ErrCode) {
	 	case '65201':
	 	case '65202':
	 	case '0':
	 		$logined_url = $logined->ErrMsg;
			if (preg_match('/token=(\d+)/', $logined_url,$matches)) {
				$token = $matches[1];
				echo "logined,get token : $token<br />\n";
			}
	 		break;
	 	case "-1":
	 		$error_msg = "System error.";
	 		break;
	 	case "-2":
	 		$error_msg = "Invalid account format";
	 		break;
	 	case "-3":
	 		$error_msg = "Invalid password";
	 		break;
	 	case "-4":
	 		$error_msg = "This account does not exist.";
	 		break;
	 	case "-5":
	 		$error_msg = "Access prohibited";
	 		break;
	 	case "-6":
	 		$error_msg = "Can't login,Need enter the verification code";
	 		break;
	 	case "-7":
	 		$error_msg = "This account has been linked with a private WeChat ID and cannot be used to log in to WeChat Official Account Admin Platform.";
	 		break;
	 	case "-8":
	 		$error_msg = "This email address has been linked with another WeChat ID.";
	 		break;
	 	case "-32":
	 		$error_msg = "Incorrect verification code";
	 		break;
	 	case "-200":
	 		$error_msg = "Due to frequent failed login attempts, you may not login on this account.";
	 		break;
	 	case "-94":
	 		$error_msg = "Please sign in using your email address.";
	 		break;
	 	case "10":
	 		$error_msg = "This Conference Account has expired.";
	 		break;
	 	default:
	 		echo "login failture: ";
	 		break;
	} 
	if (empty($logined_url)) {
		echo "Can't login. " . $error_msg;
		exit;
	}
	return $logined_url;
}

function get_logined($logined_url) {
	global $ch,$token;
	$logined_url = 'http://admin.wechat.com' . $logined_url;
	echo "access logined : $logined_url<br />";
	curl_setopt($ch, CURLOPT_URL, $logined_url);

	$output = curl_redir_exec($ch,1);
	if ($output === false) {
		echo curl_error($ch);
		exit;
	}

	if (preg_match('/name : "Materials", \s+link : \'([^\']+)\'/', $output,$matches)) {
		return $matches[1];
	} else {
		return '';
	}
}

function get_materials($materials_url) {
	global $ch,$token,$db;
	$materials_url = 'http://admin.wechat.com' . $materials_url;
	echo "access materials url and parse<br />\n";

	$list = array();
	if (! is_null($db)) {
		$sql = "SELECT `appmsgid`,`itemidx`,`title`,`pageview`,`vistor` FROM dx_weixin_article";
		$sth = mysql_query($sql);

		while ($row = mysql_fetch_array($sth)) {
			$list[$row['appmsgid'] . '/' . $row['itemidx']] = array(
				'title' => $row['title'],
				'pageview' => $row['pageview'],
				'vistor' => $row['vistor']);
		}	
	}
	
	$pageidx = 0;

	$stat_url = 'http://admin.wechat.com/cgi-bin/statappmsg?token=' . $token . '&t=ajax-appmsg-stats&url=';
	echo "<table border=1 cellpadding=4 style='border-collapse:collapse;'><tr><td>Num</td><td>Time</td><td>MsgId</td><td>PageView</td><td>vistor</td><td>Update</td><td>Title</td></tr>\n";
	$num = 0;

	while (true) {
		$materials_url = preg_replace('/pageidx=\d+/' , 'pageidx=' . $pageidx, $materials_url); 
		$pageidx++;
		echo "<tr><td colspan=7 align=center bgcolor='#EEE'>Page $pageidx</td></tr>\n";
		curl_setopt($ch, CURLOPT_URL, $materials_url);

		$output = curl_redir_exec($ch,1);
		if ($output === false) {
			echo curl_error($ch);
			exit;
		}

		if (preg_match('/<script id="json-msglist" type="json">(.*?)<\/script>/s', $output,$matches)) {
			$materials = json_decode($matches[1]);
			if ($materials === false || ! $materials->count) {
				echo "finished";
				break;
			}
			foreach ($materials->list as $appmsg) {
				$appmsgid = $appmsg->appId;
				$time = $appmsg->time;
				foreach ($appmsg->appmsgList as $itemidx => $item) {
					$itemidx++;
					curl_setopt($ch, CURLOPT_URL, $stat_url . urlencode($item->url));
					$output = curl_redir_exec($ch);
					$stat = json_decode($output);

					$pageview = $stat->PageView;
					$vistor = $stat->UniqueView;

					$updated = '';
						
					if (isset($list[$appmsgid . '/' . $itemidx])) {
						$exist = $list[$appmsgid . '/' . $itemidx];
						if (! is_null($db)) {
							$sql = "UPDATE dx_weixin_article SET
								`pageview` = {$pageview},`vistor` = {$vistor}
								WHERE (`appmsgid` = '{$appmsgid}' AND `itemidx` = {$itemidx})";
							mysql_query($sql) or die(mysql_error());
  							if (mysql_affected_rows()) {
								$updated = ($pageview - $exist['pageview']) . '/' . ($vistor - $exist['vistor']);
							}
						}
						$title = addslashes($exist['title']);
					} else {
						$url = addslashes($item->url);
						if (preg_match('/\/cgi-bin\/proxy\?url=(.*)/', $item->imgURL,$matches)) {
							$img_url = urldecode($matches[1]);
							$img_url = addslashes($img_url);
						}
						
						$title = addslashes($item->title);
						$desc = addslashes($item->desc);
						if (! is_null($db)) {

							$sql = "INSERT dx_weixin_article SET
								`appmsgid` = '{$appmsgid}', `itemidx` = {$itemidx},`time` = '{$time}',
								`img_url` = '{$img_url}',`url` = '{$url}',
								`title` = '{$title}',`desc` = '{$desc}',
								`pageview` = {$pageview},`vistor` = {$vistor}";
							$updated = 'N';
							mysql_query($sql) or die(mysql_error());
						}
					}
					$bgcolor = ($pageview < 500)?'#FFF':(($pageview < 1000)?'#F8F8D0':(($pageview < 2000)?'#FF0':'#F00'));
					$num++;
					echo "<tr style='background-color:$bgcolor;'><td>$num</td><td>$time</td><td>{$appmsgid}/{$itemidx}</td><td>$pageview</td><td>$vistor</td><td>$updated</td><td>$title</td></tr>\n";
				}
			}
			flush();
		} else {
			echo "not found materials";
		}
		
		if (! isset($_GET['all'])) break;
	}
	echo "</table>\n";
}

function curl_redir_exec($ch,$with_header = 0){
	static $curl_loops = 0;
	static $curl_max_loops = 20;
	if ($curl_loops++ >= $curl_max_loops)
	{
	    $curl_loops = 0;
	    return FALSE;
	}
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
	$data = curl_exec($ch);
	list($header, $body) = explode("\r\n\r\n", $data, 2);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($http_code == 301 || $http_code == 302)
	{
	    $matches = array();
	    preg_match('/Location:(.*?)\n/i', $header, $matches);
	    $url = @parse_url(trim(array_pop($matches)));
	    if (!$url)
	    {
	        //couldn't process the url to redirect to
	        $curl_loops = 0;
	        return ($with_header)? $data : $body;
	    }
	    $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
	    if (!$url['scheme'])
	        $url['scheme'] = $last_url['scheme'];
	    if (!$url['host'])
	        $url['host'] = $last_url['host'];
	    if (!$url['path'])
	        $url['path'] = $last_url['path'];
	    $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
	    curl_setopt($ch, CURLOPT_URL, $new_url);
	    echo 'Redirecting to ', $new_url . "<br />\n";
	    return curl_redir_exec($ch,$with_header);
	} else {
	    $curl_loops=0;
	    return ($with_header)? $data : $body;
	}
}