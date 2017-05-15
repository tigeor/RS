<?php

function get_page($url, $nobody = false, $post=array()) {
    $ttl = 10;
    set_time_limit($ttl + 20);
	$log_str = "get_page($url)";
	if (!empty($post)) {
		$log_str .= " POST(";
		foreach($post as $key => $value) {
			$log_str .= "$key => $value, ";
		}
		$log_str = preg_replace('/, $/', ')', $log_str);
	}
    log_write($log_str);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    // curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, $ttl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_COOKIESESSION, FALSE);
    curl_setopt($ch, CURLOPT_COOKIEFILE, SCRIPT_DIR . $_SESSION['login'] . '_cookie.txt');
    curl_setopt($ch, CURLOPT_REFERER, $GLOBALS['url_referer']);
    curl_setopt($ch, CURLOPT_NOBODY, $nobody);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

    if (!empty($post)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }

    log_write("curl_exec");
    $html = curl_exec($ch);
    log_write("result size: " . strlen($html));

    if (($html == false) and !$nobody) {
        $msg = sprintf("cURL error (number: %d): %s %s", curl_errno($ch), curl_error($ch), $url);
        echo($msg . "<br>\n");
        log_write($msg);

        log_write("2nd attempt");
        $ttl = 10;
        set_time_limit($ttl + 20);
        $html = curl_exec($ch);
        log_write("result size: " . strlen($html));
        if (($html == false) and !$nobody) {
            $msg = sprintf("cURL error (number: %d): %s %s", curl_errno($ch), curl_error($ch), $url);
            echo($msg . "<br>\n");
            unlink(SCRIPT_DIR . $_SESSION['login'] . '_cookie.txt');
            log_write($msg);
        }
    }
    curl_close($ch);

    $GLOBALS['url_referer'] = $url;
    log_write('fetch completed');
    return $html;
}

function login($url, $login, $pwd) {
    $ttl = 10;
    set_time_limit($ttl + 20);
    $cookie_file = SCRIPT_DIR . $login . '_cookie.txt';
	log_write("Login attempt: " . $url);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_REFERER, $GLOBALS['url_referer']);
    curl_setopt($ch, CURLOPT_COOKIESESSION, FALSE);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $GLOBALS['credentials']);

    log_write("curl_exec");
    $html = curl_exec($ch);
    log_write("result size: " . strlen($html));
    curl_close($ch);

    $GLOBALS['url_referer'] = $url;
    return $html;
}

// checks if a login is required
function login_required($html) {
	$credentials = $GLOBALS['credentials'];
	$name = key($credentials);
    if (($html == '') or preg_match('#<input.*name="'. $name . '"#', $html)) {
        return true;
    }
    return false;
}

// makes a login attempt and loads the requested URL
function do_login() {
    $html = login($GLOBALS['login_url'], $_SESSION['login'], $_SESSION['password']);
    if (login_required($html)) {
		file_put_contents('login_fail_' . date('ymd_His') . '.html', $html);
        exit_script("Login failed");
    }

    return $html;
}

?>
