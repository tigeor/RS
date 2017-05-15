<?php

/*****************8
 * New version since season 13
 * 24.10.2014
 */

session_start();
require('config.php');
require('webfunc.php');
require('miscfunc.php');

$script_dir = preg_replace("#[^/\\\]+$#", '', $argv[0]);
define('SCRIPT_DIR', $script_dir);
define('LOGFILE', 'php://stdout');
$command = SCRIPT_DIR . $start_file;

$url_referer = '';

$help_params = array('-h', '--help', '/?');
if (($argc > 1) and in_array($argv[1], $help_params)) {
    echo "Usage:\n";
    echo "         php -f " . $argv[0] . " <team ID>\n";
    echo "         php -f " . $argv[0] . " <team URL>\n";
    exit(0);
}

if ($argc == 1) {
    $teamID = 0;
} else {
    if (is_int($argv[1])) {
        $teamID = intval($argv[1]);
    } else {
        $teamID = preg_replace('#.*team-([0-9]+).*#', '$1', $argv[1]);
    }
}

$players = fetch_players($teamID);
store_players($teamID, $players);

function get_skill($elem) {
	// return numeric value no matter if the skill is represented by balls or numbers
	$value = $elem->textContent;

	if ($spans->length > 0) {
		$elem = $elem->firstChild;
		if (($elem->nodeName != '#text') and ($elem->getAttribute('title'))) {
			$value = $elem->getAttribute('title');
		}
	}

	$value = trim($value);
	return $value;
}

function fetch_players($teamID) {
    if ($teamID == 0) {
        $url = 'http://rockingsoccer.com/bg/soccer/players';
        $html = get_page($url);
        if (login_required($html)) {
            $html = do_login();
        }
        $html = get_page($url);

        $url = 'http://rockingsoccer.com/bg/soccer/players/bsquad';
        $html.= get_page($url);
    } else {
        $url = 'http://rockingsoccer.com/bg/soccer/info/team-'. intval($teamID) . '/players';
        $html = get_page($url);
    }

	$docObj = new DOMDocument();
    if (!@$docObj->loadHTML($html)) {
        exit_script("Error loading HTML");
    }
    $xpath = new DOMXPath($docObj);

	$players_links = $xpath->query('//a');
    $l = $players_links->length;
	for ($i=0; $i < $l; $i++) {
		$href = $players_links->item($i)->getAttribute('href');
        if (preg_match('#.*player-([0-9]+)#', $href, $matches)) {
            $players[$matches[1]] = array('href' => $href);
        }
    }

    foreach ($players as $playerID => $player_info) {
        $players[$playerID] = get_player_info($playerID);
    }

    return $players;
}

function get_player_info($id) {
    $player['href'] = 'http://rockingsoccer.com/bg/soccer/info/player-' . intval($id);

    $html = get_page($player['href']);
    file_put_contents("player.html", $html);

	$docObj = new DOMDocument();
    if (!@$docObj->loadHTML($html)) {
        exit_script("Error loading HTML");
    }
    $xpath = new DOMXPath($docObj);

    $elem_content = $xpath->query('//div[@id="content"]')->item(0);
    $player['name'] = trim($xpath->query('.//h2', $elem_content)->item(0)->textContent);

    $elem_table1 = $xpath->query('.//table', $elem_content)->item(0);
    $cells = $xpath->query('.//td', $elem_table1);

    $player = fetch_player_info($cells, $player);

    $elem_table2 = $xpath->query('.//table', $elem_content)->item(1);
    $cells = $xpath->query('.//td', $elem_table2);
    $player['skill_talent'] = get_skill($cells->item(3));
    $player['skill_stamina'] = get_skill($cells->item(6));
    $player['skill_strength'] = get_skill($cells->item(9));
    $player['skill_speed'] = get_skill($cells->item(12));

    $player['skill_scoring'] = get_skill($cells->item(4));
    $player['skill_passing'] = get_skill($cells->item(7));
    $player['skill_tackle'] = get_skill($cells->item(10));
	// print_r($player);
    $player['skill_blocking'] = get_skill($cells->item(13));
    $player['skill_tactics'] = get_skill($cells->item(16));

    $player['xp_forward'] = get_skill($cells->item(5));
    $player['xp_middfielder'] = get_skill($cells->item(8));
    $player['xp_defender'] = get_skill($cells->item(11));
    $player['xp_goalkeeper'] = get_skill($cells->item(14));
    $player['xp_side'] = trim($cells->item(17)->textContent);

    return $player;
}

function fetch_player_info($cells, $player) {
    $i = 0;
    $player['nationality'] = '';
    $player['language'] = '';
    $player['age'] = 0;
    $player['age_factor'] = 'Unknown';
    $player['U21'] = 'N/A';
    $player['wage_weekly']  = 0;
    $player['wage_yearly'] = 0;
    $player['position'] = 'Unknown';
    $player['class'] = 0;
    $player['class_youth'] = '';
    $player['xp'] = 0;
    $player['skills_special'] = '';
    $player['fitness'] = 0;
    $player['club'] = '';
    $player['market_price'] = 0;
    $player['nat_team'] = '';

    if ($cells->item($i)->hasChildNodes()) {
        $a = $cells->item($i)->lastChild;
        if (($a->nodeName == 'a') and ($a->hasChildNodes()) and ($a->firstChild->nodeName == 'img') and preg_match('#/img/flags/#', $a->firstChild->getAttribute('src'))) {
            $player['nationality'] = trim($cells->item($i)->textContent);

            if (++$i >= $cells->length) {
                return $player;
            }
        }
    }

    if ($cells->item($i)->hasChildNodes() and ($cells->item($i)->childNodes->length == 1)) {
        $player['language'] = trim($cells->item($i)->textContent);

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    $age_string = trim($cells->item($i)->textContent);
    if (preg_match('#([0-9]+).*,\s*([0-9]+).*\(([0-9]+)%#', $age_string, $matches)) {
        $player['age'] = $matches[1] + $matches[2]/52;
        $player['age_factor'] = $matches[3] / 100;

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    $age_string = trim($cells->item($i)->textContent);
    if (preg_match('/^[12][0-9]$/', $age_string) or preg_match('/^21 \(.*21.*\)$/', $age_string)) {
        $player['U21'] = intval($cells->item($i)->textContent);

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    $wage_string = trim($cells->item($i)->textContent);
    if (preg_match('/^([^0-9]+[0-9]{1,3})+$/', $wage_string)) {
        $player['wage_weekly'] = preg_replace('#[^0-9]*#', '', $wage_string);
        if (++$i >= $cells->length) {
            return $player;
        }
    }

    $wage_string = trim($cells->item($i)->textContent);
    if (preg_match('/^([^0-9]+[0-9]{1,3})+$/', $wage_string)) {
        $player['wage_yearly'] = preg_replace('#[^0-9]*#', '', $cells->item($i)->textContent);
        if (++$i >= $cells->length) {
            return $player;
        }
    }

    if ($cells->item($i)->hasChildNodes() and ($cells->item($i)->childNodes->length == 1)) {
        $player['position'] = trim($cells->item($i)->textContent);

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    if ($cells->item($i)->hasChildNodes() and ($cells->item($i)->firstChild->nodeName == 'span') and preg_match('/star-[a-z]+-skill|numeric-skill/', $cells->item($i)->firstChild->getAttribute('class'))) {
        $player['class'] = trim(get_skill($cells->item($i)->firstChild));
		if (preg_match_all('/[0-9]\.[0-9][0-9]/', $player['class'], $matches)) {
        // if (preg_match('/([0-9]\.[0-9]+)[^0-9]+-[^0-9]+([0-9.]+)/', $player['class'], $matches)) {
            $player['class'] = $matches[0][0];
            $player['class_youth'] = $matches[0][1];
        } else {
            $player['class'] = preg_replace('/[^0-9.]/', '', $player['class']);
        }

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    $player['xp'] = preg_replace('#[^0-9]#', '', trim($cells->item($i)->textContent));
	if (++$i >= $cells->length) {
		return $player;
    }

    $player['skills_special'] = trim($cells->item($i)->textContent);
    if (++$i >= $cells->length) {
        return $player;
    }

    $wage_string = trim($cells->item($i)->textContent);
    if (preg_match('/^([^0-9]+[0-9]{1,3})+$/', $wage_string)) {
        $player['market_price'] = preg_replace('#[^0-9]*#', '', $cells->item($i)->textContent);
        if (++$i >= $cells->length) {
            return $player;
        }
    }

    if ($cells->item($i)->hasChildNodes() and ($cells->item($i)->firstChild->nodeName == 'div') and ($cells->item($i)->firstChild->getAttribute('class') == 'energy')) {
        $fitness = trim(get_skill($cells->item($i)->firstChild));
        if (preg_match('#([0-9]+\.[0-9]+)#', $fitness, $matches)) {
            $player['fitness'] = $matches[1];
        }

        if (++$i >= $cells->length) {
            return $player;
        }
    }

    if ($cells->item($i)->hasChildNodes()) {
        $a = $cells->item($i)->firstChild;
        if (($a->nodeName == 'a') and ($a->hasChildNodes()) and ($a->firstChild->nodeName == 'img') and preg_match('#/img/flags/#', $a->firstChild->getAttribute('src'))) {
            $player['club'] = trim($cells->item($i)->textContent);

            if (++$i >= $cells->length) {
                return $player;
            }
        }
    }

    if ($cells->item($i)->hasChildNodes()) {
        $a = $cells->item($i)->firstChild;
        if (($a->nodeName == 'a') and ($a->hasChildNodes()) and ($a->firstChild->nodeName == 'img') and preg_match('#/img/flags/#', $a->firstChild->getAttribute('src'))) {
            $player['nat_team'] = trim($cells->item($i)->textContent);
        }
    }

    return $player;
}

function store_players($team, $players) {
    $filename = sprintf('players_%02d_%02d_%02d_%02d_%02d.csv', date('Y'), date('m'), date('d'), date('H'), date('i'));
	if ($team > 0) {
		$filename = sprintf('team%d_%s', $team, $filename);
	}
    $fp = fopen($filename, 'w');
    if ($fp) {
        fwrite($fp, '"First name";"Last name";"Nationality";"Position";"Flank";"Main squad";"Class";"Youth class";"Fitness";"Injured";"Age";"U21";"Weekly wage";"Yearly wage";"Market value";"Talent";"Endurance";"Power";"Speed";"Blocking";"Dueling";"Passing";"Scoring";"Tactics";"Special attributes";"XP goalkeeping";"XP defense";"XP middfield";"XP attack";"ID";"URL";"Language";"Age factor";"Club";"National Team";"XP";' . "\n"); 

        foreach ($players as $id => $player) {
			list($firstname,$lastname) = preg_split('/ /', $player['name'], 2);
            fwrite($fp, '"' . $firstname . '";');
            fwrite($fp, '"' . $lastname . '";');
            fwrite($fp, '"' . $player['nationality'] . '";');
            fwrite($fp, '"' . $player['position'] . '";');
            fwrite($fp, '"' . $player['xp_side'] . '";');
            fwrite($fp, '"";'); // main or B-squad
            fwrite($fp, '"' . $player['class'] . '";');
            fwrite($fp, '"' . $player['class_youth'] . '";');
            fwrite($fp, '"' . $player['fitness'] . '";');
            fwrite($fp, '"";'); // injured
            fwrite($fp, '"' . $player['age'] . '";');
            fwrite($fp, '"' . $player['U21'] . '";'); // under 21
            fwrite($fp, '"' . $player['wage_weekly'] . '";');
            fwrite($fp, '"' . $player['wage_yearly'] . '";');
            fwrite($fp, '"' . $player['market_price'] . '";');

            fwrite($fp, '"' . $player['skill_talent'] . '";');
            fwrite($fp, '"' . $player['skill_stamina'] . '";');
            fwrite($fp, '"' . $player['skill_strength'] . '";');
            fwrite($fp, '"' . $player['skill_speed'] . '";');
            fwrite($fp, '"' . $player['skill_blocking'] . '";');
            fwrite($fp, '"' . $player['skill_tackle'] . '";');
            fwrite($fp, '"' . $player['skill_passing'] . '";');
            fwrite($fp, '"' . $player['skill_scoring'] . '";');
            fwrite($fp, '"' . $player['skill_tactics'] . '";');
            fwrite($fp, '"' . $player['skills_special'] . '";');
            fwrite($fp, '"' . $player['xp_goalkeeper'] . '";');
            fwrite($fp, '"' . $player['xp_defender'] . '";');
            fwrite($fp, '"' . $player['xp_middfielder'] . '";');
            fwrite($fp, '"' . $player['xp_forward'] . '";');

            fwrite($fp, '"' . $id . '";');
            fwrite($fp, '"' . $player['href'] . '";');
            fwrite($fp, '"' . $player['language'] . '";');
            fwrite($fp, '"' . $player['age_factor'] . '";');
            fwrite($fp, '"' . $player['club'] . '";');

            fwrite($fp, '"' . $player['nat_team'] . '";');
            fwrite($fp, '"' . $player['xp'] . '";');
            fwrite($fp, "\n");
        }

        fclose($fp);

		echo("\n\nResults stored in $filename\n\n");
    } else {
        echo("\n\nCan't open file $filename for storing\n\n");
    }

    return true;
}

?>
