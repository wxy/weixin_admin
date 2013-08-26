<?php
define('VERSION','0.5.2.20130826');
define('OUTPUT_DELIMITER',"\n\n");

// global config
/* cookiejar dir,need writable */
$cookiejar_dir = '/tmp';
/* set your admin username and password */
/* if not set, will prompt it by auth dialog */
$admin_user    = '';
$admin_pass    = '';
/* database setting */
$db_host       = 'localhost';
$db_user       = '';
$db_pass       = '';
$db_name       = '';
$db_table      = 'weixin_article';
/* debug flag */
$debug         = 0;
/* color set */
$color_ranks = array(
	0    => 'color_rank1', 
	500  => 'color_rank2', 
	1000 => 'color_rank3', 
	2000 => 'color_rank4', 
	4000 => 'color_rank5');

// output format
$output_format = (isset($_GET['output']) && $_GET['output'] == 'json')?'json':'html';

ob_start();

// user local config 
if (file_exists('materials_conf.php')) require_once('materials_conf.php');

if ($output_format == 'json') {
	ob_end_clean();
	ob_start();
}
register_shutdown_function('finish');

// global variables
/* cookie jar */
$cookiejar = get_cookiejar($cookiejar_dir); 
/* database */
$db = get_database($db_host,$db_user,$db_pass,$db_name);

// processing
init();

/* session auth token */
$token = do_login($admin_user,$admin_pass);

/* slave user */
$slave_user = get_slave_user();

//get_logined();

get_materials();


/**
 * get cookie jar
 * @return string $cookiejar 
 */
function get_cookiejar() {
	global $cookiejar_dir;

	if (! is_dir($cookiejar_dir) || ! is_writable($cookiejar_dir)) {
		$cookiejar_dir = ini_get('upload_tmp_dir');
	}
	if (! is_dir($cookiejar_dir) || ! is_writable($cookiejar_dir)) error("couldn't write cookiejar dir : " . $cookiejar_dir);
	
	$cookiejar = $cookiejar_dir . '/weixin_admin.cookie.' . posix_getpid();

	if (! file_exists($cookiejar)) touch($cookiejar);
	if (! is_writable($cookiejar)) error("couldn't create cookiejar : " . $cookiejar);

	return $cookiejar;
}
/**
 * get cookie from cookiejar
 * @param string $cookie_name
 * @return string $cookie
 */
function get_cookie($cookie_name) {
	global $cookiejar;

	$lines = file($cookiejar);
	foreach ($lines as $line) {
		if (substr($line,0,1) == '#') continue;
		$fields = preg_split('/\s+/', $line);
		if (isset($fields[6]) && $fields[5] == $cookie_name) return $fields[6];
	}
	return null;
}

/**
 * init
 */
function init() {
	global $output_format;
	if ($output_format == 'html') {
		echo "<html><head><title>Materials of Weixin Official Account</title>\n";
		echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />\n";
		echo "</head><body>";		
	} else if ($output_format == 'json') {
		header("Content-Type:application/json");		
	}
}
/**
 * get browser object
 * @return object $ch browser agent 
 */
function get_agent() {
	global $cookiejar;
	if(! extension_loaded('curl')) error("Fatal : couldn't found curl extension in PHP! Please enable it.");
	
	$user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FAILONERROR, true); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);

	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);

	return $ch;
}

/**
 * get database object
 * @param string $db_host
 * @param string $db_user
 * @param string $db_pass
 * @param string $db_name
 * @return object $db database object
 */
function get_database($db_host,$db_user,$db_pass,$db_name) {
	$db = null;
	if (! empty($db_host) && ! empty($db_user) && ! empty($db_name)) {
		$db = mysql_connect($db_host,$db_user,$db_pass);
		mysql_select_db($db_name);
		mysql_query("SET NAMES UTF8");
	} else {
		message("not found database setting, don't use database support.");
	}

	return $db;
}
/**
 * do login and get token
 * @param string $admin_user 
 * @param string $admin_pass
 * @return string $token
 */
function do_login($admin_user,$admin_pass) {
	$ch = get_agent();

	$refer_url = 'http://admin.wechat.com/cgi-bin/loginpage?t=wxm2-login&lang=en_US';
	$login_url = 'http://admin.wechat.com/cgi-bin/login?lang=en_US';

	if ($admin_user === '' || $admin_pass === '') {
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$admin_user = $_SERVER['PHP_AUTH_USER'];
			$admin_pass = $_SERVER['PHP_AUTH_PW'];
		} else {
			header('WWW-Authenticate: Basic realm="Weixin Official Account Authenticate"');
		    header('HTTP/1.0 401 Unauthorized');
		    message("Please enter your weixin official account and password.");
		    exit;
		}
	}
	debug("do login...");

	$post_data = array(
		'username' => $admin_user,
		'pwd'      => md5(substr($admin_pass,0,16)),
		'imgcode'  => '',
		'f'        => 'json');
	curl_setopt($ch, CURLOPT_URL, $login_url);
	curl_setopt($ch, CURLOPT_REFERER, $refer_url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$output = curl_redir_exec($ch);
	if ($output === false) error(curl_error($ch));

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
				debug("logined,get token : $token");
				return $token;
			}
	 		break;
		case "-1": 		$error_msg = "System error."; break;
		case "-2": 		$error_msg = "Invalid account format"; break;
		case "-3": 		$error_msg = "Invalid password"; break;
		case "-4": 		$error_msg = "This account does not exist."; break;
		case "-5": 		$error_msg = "Access prohibited"; break;
		case "-6": 		$error_msg = "Can't login,Need enter the verification code"; break;
		case "-7": 		$error_msg = "This account has been linked with a private WeChat ID and cannot be used to log in to WeChat Official Account Admin Platform."; break;
		case "-8": 		$error_msg = "This email address has been linked with another WeChat ID."; break;
		case "-32": 	$error_msg = "Incorrect verification code"; break;
		case "-200":	$error_msg = "Due to frequent failed login attempts, you may not login on this account."; break;
		case "-94": 	$error_msg = "Please sign in using your email address."; break;
		case "10": 		$error_msg = "This Conference Account has expired."; break;
		default: 		$error_msg = "unknown error."; break;
	} 
	// login error 
	header('WWW-Authenticate: Basic realm="Weixin MP Authenticate"');
    header('HTTP/1.0 401 Unauthorized');
    error($error_msg);
    exit;

}

/**
 * get slave user
 * @return string $slave_user 
 */
function get_slave_user() {
	$slave_user = get_cookie('slave_user');
	debug("get slave_user : " . $slave_user);	
	return $slave_user;
}
/**
 * access logined page 
 */
function get_logined() {
	global $token;

	$ch = get_agent();
	
	$logined_url = 'http://admin.wechat.com/cgi-bin/indexpage?t=wxm-index&lang=en_US&token=' . $token;

	debug("access logined page");
	curl_setopt($ch, CURLOPT_URL, $logined_url);

	$output = curl_redir_exec($ch);
	if ($output === false) error(curl_error($ch));

	if (preg_match('/name : "Materials", \s+link : \'([^\']+)\'/', $output,$matches)) {
		return $matches[1];
	} else {
		error("couldn't get materials url.");
	}
}

/**
 * access materials page and parse it
 */
function get_materials() {
	global $token,$slave_user,$db,$db_table,$color_ranks,$output_format;

	$ch = get_agent();

	$materials_url = 'http://admin.wechat.com/cgi-bin/operate_appmsg?sub=list&type=10&subtype=3&t=wxm-appmsgs-list-new&pagesize=10&pageidx=0&lang=en_US&token=' . $token;

	debug("access materials page and parse it.");
	if ($output_format == 'html') flush();

	$slave_user = addslashes($slave_user);
	$list = array();
	if (! is_null($db)) {
		$sql = "SELECT `appmsgid`,`itemidx`,`pageview`,`vistor`,`sent_date` FROM `{$db_table}`
			WHERE `slave_user` = '{$slave_user}' ";
		$query = mysql_query($sql);

		while ($row = mysql_fetch_array($query)) {
			$list[$row['appmsgid'] . '-' . $row['itemidx']] = array(
				'pageview' => $row['pageview'],
				'vistor'   => $row['vistor'],
				'sent_date'=> $row['sent_date']);
		}	
	}
	
	// default start from page 0
	$pageidx = (isset($_GET['pageidx']) && $_GET['pageidx'] > 0)? intval($_GET['pageidx']):0;
	// total fetched pages. default fetch one page. set to -1 will fetch all pages 
	$pages = (isset($_GET['pages']) && is_numeric($_GET['pages']))? $_GET['pages']:1;

	if (isset($_GET['now'])) {
		// only get current day sent
		// limit fetch first page
		$pageidx = 0;
		$pages = 1;
	}
	
	if ($output_format == 'html') {
		echo "<table border=1 cellpadding=4 style='border-collapse:collapse;'>";
		echo "<thead><tr><th>Time/Sent</th><th>MsgId</th><th>Index</th><th>Page View</th><th>Vistor</th><th>Update</th><th>P/V</th><th>Title</th></tr></thead><tbody>\n";
	}

	$total_material = 0;
	$total_item     = 0;
	$total_pageview = 0;
	$total_vistor   = 0;
	$avg_ppv        = 0;

	$fetched_pages = 0;
	while (++$fetched_pages) {
		$materials_url = preg_replace('/pageidx=\d+/' , 'pageidx=' . $pageidx++, $materials_url); 
	
		curl_setopt($ch, CURLOPT_URL, $materials_url);

		$output = curl_redir_exec($ch);
		if ($output === false) error(curl_error($ch));

		if (! preg_match('/<script id="json-msglist" type="json">(.*?)<\/script>/is', $output,$matches)) {
			break;
		}

		$materials = json_decode($matches[1]);
		if ($materials === false || ! $materials->count) {
			break;
		}

		if ($output_format == 'html') {
			echo "<tr><td colspan=8 align=center bgcolor='#EEE'>Page $pageidx</td></tr>\n";
		}
		foreach ($materials->list as $material) {
			$appmsgid = $material->appId;
			$time     = $material->time;
			$count    = $material->count;
			$total_material++;

			$sent_date = 0;
			$output = array();
			foreach ($material->appmsgList as $itemidx => $item) {
				$itemidx++;
				
				$msgid = $appmsgid . '-' . $itemidx;
				$exist = $list[$msgid];

				// get sent
				$new_get_sent = false;
				if ($itemidx == 1) {
					if (isset($exist) && $exist['sent_date']) {
						$sent_date = $exist['sent_date'];
					} else {
						// new get sent
						$sent_date = get_sent($ch,$item->title,$time);
						$new_get_sent = true;
					}
				
					if (isset($_GET['now'])) {
						// only get now sent
						if (date("Y-m-d",$sent_date) != date("Y-m-d")) {
							$total_material--;
							break;
						}
					}
				} else {
					$sent_date = 0;
				}

				$stat = get_stat($ch,$item->url);

				$pageview = $stat['PageView'];
				$vistor   = $stat['UniqueView'];
				
				$updated  = '';

				if (isset($exist)) {
					// update
					if (! is_null($db)) {
						$sql = "UPDATE `{$db_table}` SET
							`pageview` = {$pageview},`vistor` = {$vistor},`sent_date` = {$sent_date}
							WHERE (`slave_user` = '{$slave_user}' AND `appmsgid` = '{$appmsgid}' AND `itemidx` = {$itemidx})";
						mysql_query($sql) or error(mysql_error());
						if (($pageview != $exist['pageview']) || ($vistor != $exist['vistor'])) {
							$updated = '+ ' . ($pageview - $exist['pageview']) . '/' . ($vistor - $exist['vistor']);
						}
					}
				} else {
					// new
					if (preg_match('/\/cgi-bin\/proxy\?url=(.*)/', $item->imgURL,$matches)) {
						$img_url = urldecode($matches[1]);
						$img_url = addslashes($img_url);
					}
					
					if (! is_null($db)) {
						$title = addslashes($item->title);
						$desc  = addslashes($item->desc);
						$url   = addslashes($item->url);

						$sql = "INSERT `{$db_table}` SET
							`slave_user` = '{$slave_user}',`appmsgid` = '{$appmsgid}', `itemidx` = {$itemidx},
							`time` = '{$time}',`img_url` = '{$img_url}',`url` = '{$url}',
							`title` = '{$title}',`desc` = '{$desc}',
							`pageview` = {$pageview},`vistor` = {$vistor},`sent_date` = {$sent_date}";
						$updated = 'New';
						mysql_query($sql) or error(mysql_error());
					}
				}

				ksort($color_ranks);
				$line_style = '';
				foreach ($color_ranks as $rank => $style) {
					if ($pageview > $rank) {
						$line_style = $style;
					} else {
						break;
					}
				}

				$ppv = ($vistor != 0)?sprintf("%.2f",$pageview / $vistor):'n/a';
				
				$total_item++;
				$total_pageview += $pageview;
				$total_vistor   += $vistor;

				if ($output_format == 'html') {
					echo "<tr class='$line_style' onmouseover='this.className=\"hover\";' onmouseout='this.className=\"$line_style\";'>\n";
				}

				if ($count) {
					if ($output_format == 'html') {
						$weekday = date('w',$sent_date);
						$sent_date = "<span style='" . ($new_get_sent?'color:red;':'') . "'>" . (($sent_date)?date("Y-m-d H:i:s",$sent_date):'n/a') . "</span>";
						$date_color = ($weekday > 0 && $weekday < 6)?'white':'lightcyan';
						echo "<td rowspan=$count bgcolor=$date_color>$time<br />$sent_date</td><td rowspan=$count bgcolor=white>{$appmsgid}</td>";
					} else if ($output_format == 'json') {
						$sent_date = ($sent_date)?date("Y-m-d H:i:s",$sent_date):'n/a';
						$output = array(
							'time'      => $time, 
							'sent_date' => $sent_date, 
							'appmsgid'  => $appmsgid,
							'items'     => array());
					}
					$count = 0;
				}
				if ($output_format == 'html') {
					echo "<td>$itemidx</td><td>$pageview</td><td>$vistor</td><td>$updated</td><td>$ppv</td>";
					echo "<td><a href='{$item->url}' target=_blank>{$item->title}</a></td></tr>\n";
				} else if ($output_format == 'json') {
					array_push($output['items'],array(
						'itemidx' => $itemidx,
						'pageview' => $pageview, 
						'vistor' => $vistor, 
						'updated' => $updated, 
						'ppv' => $ppv,
						'url' => $item->url, 
						'title' => $item->title));
				}
			}
			if ($output_format == 'json' && ! empty($output)) {
				echo json_encode($output) . OUTPUT_DELIMITER;
			}
		}
		if ($output_format == 'html') flush();
		
		if ($pages != -1 && $pages <= $fetched_pages) break;
	}

	if ($output_format == 'html') {
		$avg_ppv = ($total_vistor != 0)?sprintf("%.2f",$total_pageview / $total_vistor):'n/a';
		echo "</tbody><tfoot><tr><th>count:</th><th>$total_material</th><th>$total_item</th><th>$total_pageview</th><th>$total_vistor</th><th>average:</th><th>$avg_ppv</th>\n";
		if ($pages != -1) {
			echo "<th><button onclick=\"location.href='?pages=1&pageidx={$pageidx}';\">next page</button> <button onclick=\"location.href='?pages=-1&pageidx={$pageidx}';\">total next page</button> <button onclick=\"location.href='?pages=-1';\">total page</button></th></tr></tfoot>\n";
		} else {
			echo "<th>&nbsp;</th></tr>\n";
		}

		echo "</table>\n";
	}
}

/**
 * get access stat
 * @param string $url
 * @return array $stat
 */
function get_stat($ch,$url) {
	global $token;
	$stat_url = 'http://admin.wechat.com/cgi-bin/statappmsg?token=' . $token . '&t=ajax-appmsg-stats&url=';

	//$ch = get_agent();
	// get stat
	curl_setopt($ch, CURLOPT_URL, $stat_url . urlencode($url));
	$output   = curl_redir_exec($ch);
	$stat     = json_decode($output);
	return (array) $stat;
}
/**
 * get sent date
 * @param string $title meterial title
 * @return int $datetime
 */
function get_sent($ch,$title,$date) {
	global $token;

	//$ch = get_agent();

	// sent data of all msgs
	static $sent_data = array();
	// current seeked page
	static $pageidx = 0;
	// current seeked sent date
	static $start_date = null;
	if (is_null($start_date)) $start_date = time();

	$sent_url = 'http://admin.wechat.com/cgi-bin/masssendpage?t=wxm-history&action=msgstatus&pagesize=10&pageidx=0&lang=en_US&token=' . $token;

	// found
	if (isset($sent_data[$title])) {
		return $sent_data[$title];
	}
	// material's born date
	$material_date = strtotime($date);
	// not seek before material's born
	while ($start_date > $material_date) {

		$sent_url = preg_replace('/pageidx=\d+/' , 'pageidx=' . $pageidx++, $sent_url);
		curl_setopt($ch, CURLOPT_URL, $sent_url);
		$output = curl_redir_exec($ch);

		if ($output === false) error(curl_error($ch));

		if (! preg_match('/<script type="json" id="json-msgList">(.*?)<\/script>/is', $output,$matches)) {
			break;
		}
		$msgs = json_decode($matches[1]);
		if ($msgs === false || empty($msgs)) {
			break;
		}
		foreach ($msgs as $msg) {
			if ($start_date > $msg->dateTime) {
				$start_date = $msg->dateTime;
			}
			$sent_data[$msg->title] = $msg->dateTime;
		}

		if (isset($sent_data[$title])) {
			return $sent_data[$title];
		}
	}
	return 0;
}
/**
 * finish
 */
function finish() {
	global $cookiejar,$output_format;
	unlink($cookiejar);

	if ($output_format == 'html') {
		echo "<p><a href='http://wxy.github.io/weixin_admin/' target=_blank>weixin_admin</a> " . VERSION . ", maintains by <a href='http://wxy.github.io/' target=_blank>wxy</a>.</p>\n";
		echo "</body></html>\n";
	} else if ($output_format == 'json') {
		$output = explode(OUTPUT_DELIMITER, ob_get_clean());
		// chop last null
		$last = array_pop($output);
		if (! empty($last)) array_push($output,$last);
		echo '[' . implode(",", $output) . ']';
	}
}
/**
 * do curl exec with auto redirect
 * @param object $ch curl object
 * @param integer $return_header 
 * @return string $data
 */
function curl_redir_exec($ch,$return_header = 0){
	static $curl_loops = 0;
	$curl_max_loops = 20;
	if ($curl_loops++ >= $curl_max_loops) {
	    $curl_loops = 0;
	    return FALSE;
	}
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

	$data = curl_exec($ch);
	list($header, $body) = explode("\r\n\r\n", $data, 2);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($http_code == 301 || $http_code == 302) {
		// redirect
	    $matches = array();
	    preg_match('/Location:(.*?)\n/i', $header, $matches);

	    $url = @parse_url(trim(array_pop($matches)));
	    if (! $url) {
	        //couldn't process the url to redirect to
	        $curl_loops = 0;
	        return ($return_header)? $data : $body;
	    }
	    $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
		if (! $url['scheme']) $url['scheme'] = $last_url['scheme'];
		if (! $url['host']) $url['host']     = $last_url['host'];
		if (! $url['path']) $url['path']     = $last_url['path'];
	    $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']? '?'.$url['query'] : '');
	    curl_setopt($ch, CURLOPT_URL, $new_url);
	    //debug('Redirecting to '. $new_url);
	    return curl_redir_exec($ch,$return_header);
	} else {
		// goto end
	    $curl_loops=0;
	    return ($return_header)? $data : $body;
	}
}

/**
 * show error message and fault
 * @param string $message error 
 */
function error($message) {
	global $output_format;
	if ($output_format == 'html') {
		echo "<p style='color:red;'> $message </p>\n";
	} else if ($output_format == 'json') {
		echo json_encode(array('error' => $message)) . OUTPUT_DELIMITER;
	}
	exit;
}
/**
 * show message
 * @param string $message 
 */
function message($message) {
	global $output_format;
	if ($output_format == 'html') {
		echo "<p style='color:red;'> $message </p>\n";
	} else if ($output_format == 'json') {
		echo json_encode(array('message' => $message)) . OUTPUT_DELIMITER;
	}
}
/**
 * debug message
 * @param string $message 
 */
function debug($message) {
	global $debug,$output_format;
	if (! $debug) return false;
	if ($output_format == 'html') {
		echo "<p style='color:red;'> $message </p>\n";
	} else if ($output_format == 'json') {
		echo json_encode(array('debug' => $message)) . OUTPUT_DELIMITER;
	}
}