<?php

session_start();
require('config.php');
require('webfunc.php');
require('miscfunc.php');

$script_dir = preg_replace("#[^/\\\]+$#", '', $argv[0]);
define('SCRIPT_DIR', $script_dir);
define('LOGFILE', SCRIPT_DIR . $_SESSION['login'] . '_rs.log');
$command = SCRIPT_DIR . $start_file;
log_write("----------------------------------------------------");

// set new task in 5 minutes as protection against unexpected exit
schedule_task($task_names . 'safe', time()+300);

//*/ random delay because windows starts the scheduled tasks at 00 sec
/* DEBUG
$t = rand(0,59);
set_time_limit($t+30);
echo "Delay: $t sec\n";
sleep($t);
//*/

$url_referer = '';

$html = prepare_action('visits');
visit($html);
schedule_visits($html);

// echo $html;
delete_task($task_names . 'safe');
log_write("Script ends\r\n---------------------------------------");

function prepare_action($action) {
    $url = 'http://rockingsoccer.com/bg/soccer';
    $html = get_page($url);

    if (login_required($html)) {
        $html = do_login();
    }

    return $html;
}

function visit($html) {
	$docObj = new DOMDocument();
    if (!@$docObj->loadHTML($html)) {
        exit_script("Error loading HTML");
    }

    $xpath = new DOMXPath($docObj);

	$game_time_str = trim($xpath->query('//div[@id="gametime"]')->item(0)->textContent);
	$game_time = strtotime(date('Y-m-d ') . $game_time_str);
	$system_time = time();
	$diff_time = $system_time - $game_time;

	// echo $game_time_str . ' -> ' . date('d.m.Y H:i:s', $game_time) . "\n";
	// echo date('d.m.Y H:i:s', $system_time) . ' (diff: ' . $diff_time . " s)\n";

	$year = date('Y');
	$month = date('m');
	$day = date('d');
	$fixtures = find_games($xpath, $year, $month, $day);
	$i = 0;
	foreach ($fixtures as $id => $time) {
		$fixture_time_str = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
		$fixture_time = strtotime($fixture_time_str);
		$fixture_diff = $game_time - $fixture_time;
		if (($fixture_diff > 0) and ($fixture_diff < 600)) {
			// visit
			$html = get_page('http://rockingsoccer.com/bg/soccer/info/match-' . $id);
			if (preg_match('#<div class="live-users".*?<strong>\+[0-9].*?<img src="img/icons/credit.png#"', $html)) {
				log_write('Game ' . $id . ' visited');
			} else {
				log_write('Game ' . $id . ' not visited');
				// file_put_contents('game_' . $id . '.html', $html);
				schedule_task($GLOBALS['task_names'] . 'second_try', time()+120);
			}
			break;
		}

		$i++;
	}
}

function schedule_visits($html) {
	$docObj = new DOMDocument();
    if (!@$docObj->loadHTML($html)) {
        exit_script("Error loading HTML");
    }

    $xpath = new DOMXPath($docObj);

	$game_time_str = trim($xpath->query('//div[@id="gametime"]')->item(0)->textContent);
	$game_time = strtotime(date('Y-m-d ') . $game_time_str);
	$system_time = time();
	$diff_time = $system_time - $game_time;
	log_write("Difference between system and game server time: $diff_time sec");

	$year = date('Y');
	$month = date('m');
	$day = date('d');
	$fixtures = find_games($xpath, $year, $month, $day);
	$i = 0;
	foreach ($fixtures as $time) {
		$fixture_time = strtotime("$year-$month-$day $time");
		$fixture_time_corr = $fixture_time + $diff_time + 60 * ($i+1);
		if ($fixture_time_corr > time()) {
			schedule_task($GLOBALS['task_names'] . $i, $fixture_time_corr);
			$i++;
			if ($i > 2) {
				break;
			}
		}
	}

	$year = date('Y', time()+24*60*60);
	$month = date('m', time()+24*60*60);
	$day = date('d', time()+24*60*60);
	$fixtures = find_games($xpath, $year, $month, $day);
	foreach ($fixtures as $time) {
		$fixture_time = strtotime("$year-$month-$day $time");
		$fixture_time_corr = $fixture_time + $diff_time + 60 * ($i+1);
		schedule_task($GLOBALS['task_names'] . $i, $fixture_time_corr);
		$i++;
		if ($i > 2) {
			break;
		}
	}

	$year = date('Y', time()+48*60*60);
	$month = date('m', time()+48*60*60);
	$day = date('d', time()+48*60*60);
	$fixtures = find_games($xpath, $year, $month, $day);
	foreach ($fixtures as $time) {
		$fixture_time = strtotime("$year-$month-$day $time");
		$fixture_time_corr = $fixture_time + $diff_time + 60 * ($i+1);
		schedule_task($GLOBALS['task_names'] . $i, $fixture_time_corr);
		$i++;
		if ($i > 2) {
			break;
		}
	}

}

function find_games($xpath, $year, $month, $day) {
	$div_id = sprintf("calendar-summary-%04d-%02d-%02d", $year, $month, $day);
	log_write("Looking for fixtures on $day.$month.$year");
	$games = $xpath->query('//div[@id="' . $div_id . '"]/ul/li');
	// log_DOMNode($games);
	$l = $games->length;
	for ($i=0; $i < $l; $i++) {
		$children = $games->item($i)->childNodes;
		$ll = $children->length;
		for ($j=0; $j < $ll; $j++) {
			$child = $children->item($j);
			if ($child->nodeName == 'span') {
				$info = $child->nextSibling;
				if (($info->nodeName == 'img') and ($info->getAttribute('src') == '/img/icons/match.png')) {
					$game_time = $child->textContent;
					$nodeName = '';
					while (($child->nodeName != "a") and $child->nextSibling) {
						$child = $child->nextSibling;
					}
					$match_id = preg_replace('#^.*match-([0-9]+)[^0-9]*.*$#', '$1', $child->getAttribute('href'));
					if (isset($fixtures[$match_id])) {
						$fixtures[] = $game_time;
					} else {
						$fixtures[$match_id] = $game_time;
					}
					log_write("Fixture: $day.$month.$year $game_time");
				} else {
					log_write("Not a fixture: " . $child->textContent);
				}
				break;
			}
		}
	}

	return $fixtures;
}

?>
