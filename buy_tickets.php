<?php

/*****************8
 * New version since season 13
 * 24.10.2014
 * Improved reporting of results - 02.12.2015
 */

session_start();
require('config.php');
require('webfunc.php');
require('miscfunc.php');

$script_dir = preg_replace("#[^/\\\]+$#", '', $argv[0]);
define('SCRIPT_DIR', $script_dir);
// define('LOGFILE', 'php://stdout');
define('LOGFILE', 'ticket.log');
$command = SCRIPT_DIR . $start_file;

$url_referer = '';

$help_params = array('-h', '--help', '/?');
if (($argc != 2) or in_array($argv[1], $help_params)) {
    echo "Usage:\n";
    echo "         php -f " . $argv[0] . " <league URL>\n";
    exit(0);
}

$html = do_login();
$league_url = $argv[1];
if (preg_match('/^[a-z]{2,3}$/', $league_url)) {
	$league_url = build_league_url($argv[1]);
}

$results = array();
$results['header'] = array();

while ($league_url) {
	$league_name = league_url_to_name($league_url);
	$results[$league_name] = array();
    $league_results_page = get_page($league_url . '/results');
    $docObj = new DOMDocument();
    if (!@$docObj->loadHTML($league_results_page)) {
        exit("Error loading HTML from " . $league_url . '/results');
    }
    $xpath = new DOMXPath($docObj);
    $league_list = get_league_urls($xpath);
    $round = get_league_round($xpath);

	echo "Processing $league_name\n";
    $games = array();
    for ($i=0; $i<4; $i++) {
        if ($i > 0) {
			$results_url = $league_url . '/results/' . ($round+$i);
            $league_results_page = get_page($results_url);
            // file_put_contents('results' . $i, $league_results_page);
            $docObj = new DOMDocument();
            if (!@$docObj->loadHTML($league_results_page)) {
                exit("Error loading HTML from " . $league_url . '/results');
            }
            $xpath = new DOMXPath($docObj);
        }
        $games = get_games($xpath, $games);
    }

    foreach ($games as $game_url) {
		$tickets = buy_tickets($game_url, $results);
		if ($tickets == '') {
				$tickets = 0;
		}
		$results['header'][$tickets] = 1;
		if (isset($results[$league_name][$tickets])) {
				$results[$league_name][$tickets]++;
		} else {
				$results[$league_name][$tickets] = 1;
		}
    }

    $l = array_search($league_url, $league_list);
    if ($l >= count($league_list) - 1) {
        $league_url = '';
    } else {
        $league_url = $league_list[$l+1];
    }
}

print_results($results);

function get_league_urls($xpath) {
    $urls = array();
    $league_elements = $xpath->query('//div[@class="side"]/div[@class="menu"]//a');
    $l = $league_elements->length;
    for ($i=0; $i<$l; $i++) {
        $url = $league_elements->item($i)->getAttribute('href');
        if (!preg_match('/group-/', $url)) {
            $url .= '/group-1';
        }
        if (preg_match('/level-/', $url) and !in_array($url, $urls)) {
            $urls[] = $url;
        }
    }

    return $urls;
}

function get_league_round($xpath) {
    $round = $xpath->query('//a[@class="selected"]')->item(0)->textContent;
    return intval($round);
}

function get_games($xpath, $games) {
    $elements = $xpath->query('//a[@class="tickets-link"]');
    for ($i=0; $i < $elements->length; $i++) {
        $games[] = $elements->item($i)->getAttribute('href');
    }

    return $games;
}

function buy_tickets($game_url) {
    if (!preg_match('/fixture/', $game_url)) {
        $game_url .= '/fixture';
    }

    $html = get_page($game_url);
    $docObj = new DOMDocument();
    if (!@$docObj->loadHTML($html)) {
        file_put_contents("buy_tickets.html", $html);
        exit("Error loading HTML from " . $game_url);
    }
    $xpath = new DOMXPath($docObj);

    $options = $xpath->query('//form//select/option');
    if ($options->length > 0) {
        $option = $options->item(0);
        $v = $option->getAttribute('value');
        $type = intval(preg_replace('/.* (1?[0-9]) .*/', '$1', $option->textContent));
        if (isset($results[$type])) {
            $results[$type]++;
        } else {
            $results[$type] = 1;
        }
		// echo "$game_url\ntickets left: $type\n";
		// print_r($results);

        if (preg_match('/fixture/', $v)) {
            $post = array('return_url' => $v, 'buy_ticket' => 'Получи безплатни билети');
            $h = get_page($game_url, true, $post);
		// file_put_contents('tickets.html', $h);
        }
    }

    return $type;
}

function league_url_to_name($league_url) {
	$name = $league_url;

	preg_match('|league-([a-z]+)\.[0-9]+/level-([0-9]+)|', $league_url, $matches);
	$name = $matches[1] . ' Level ' . $matches[2];

	if (preg_match('/group-([0-9])+/', $league_url, $matches)) {
		$name = sprintf('%s Group %02d', $name, $matches[1]);
	}

	return $name;
}

function print_results($results) {
	// print_r($results);
	$header = 'League \ Tickets';
	$header = sprintf('%-20s', $header);
	$columns = count($results['header']);

	foreach ($results['header'] as $count => $not_interesting) {
		$header = sprintf('%s%3d', $header, $count);
	}

	echo "\n\n";
	echo $header . "\n";
	echo str_repeat("-", 20);
	echo str_repeat(" --", $columns);
	echo "\n";

	foreach ($results as $league => $tickets) {
		if ($league == 'header') {
			continue;
		}
		$row = sprintf("%-20s", $league);
		foreach($results['header'] as $c => $not_interesting) {
			if (!isset($tickets[$c])) {
				$tickets[$c] = 0;
			}
			$row = sprintf('%s%3d', $row, $tickets[$c]);
		}
		echo $row . "\n";
	}
}

function build_league_url($code) {
	$season = intval((time() - strtotime('2013/02/06')) / (60*60*24*52)) + 1;
	$league_url = "http://rockingsoccer.com/bg/soccer/league-$code.$season/level-1/group-1";

	return $league_url;
}

?>
