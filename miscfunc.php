<?php

function log_write($str) {
    $result = false;
    $fp = fopen(LOGFILE, 'a');
    $login = '<no login>';
    if ($fp) {
        fprintf($fp, "[%s]: %s\r\n", date('d.m.Y H:i:s'), $str);
        fclose($fp);
        $result = true;
    }

    return $result;
}

function log_DOMNode($elem) {
    log_write('contents of DOM Node');
    log_write(' Name: ' . $elem->nodeName);
    // log_write(' Value: ' . $elem->nodeValue);
    // log_write(' Type: ' . $elem->nodeType);
    // log_write(' local name: ' . $elem->localName);
    log_write(' text content: ' . $elem->textContent);
    log_write(' child nodes: ' . $elem->childNodes->length);
    log_write('Node traced');
}

// schedule task name, year, month, date and time to run the task
function schedule_task($name, $time) {
    $sch_user = $GLOBALS['schedule_task_user'];
    $sch_date = date('d/m/Y', $time);
    $sch_time = date('H:i:s', $time);
    $task = '';

    $os_name = php_uname('s');
    $os_ver = preg_replace('/\..*/', '', php_uname('r'));

    // Windows 7
    if (($os_name == 'Windows NT') and ($os_ver == 6)) {
        $command = 'schtasks /query /tn ' . $name;
        exec($command, $output, $return);
        if (preg_match("/$name/", implode($output, ' '))) {
            //task exists - change its time
            $task = sprintf('schtasks /change %s /tn %s /st %s /sd %s', $sch_user, $name, $sch_time, $sch_date);
        } else {
            //task does not exist - create new
            $task = sprintf('schtasks /create %s /sc once /tn %s /tr \"%s\" /st %s /sd %s', $sch_user, $name, $GLOBALS['command'], $sch_time, $sch_date);
        }

    // Windows XP
    } elseif (($os_name == 'Windows NT') and ($os_ver == 5)) {
        $task = sprintf('schtasks /create %s /sc once /tn %s /tr \"%s\" /st %s /sd %s', $sch_user, $name, $GLOBALS['command'], $sch_time, $sch_date);

        $command = 'schtasks /query';
        exec($command, $output, $return);
        if (preg_match("/$name/", implode($output, ' '))) {
            //task exists - delete it before creating a new one
            @exec('schtasks /delete /f /tn ' . $name, $output, $return);
            if ($return) {
                log_write("Cannot delete task $name. Reason: $output");
            }
        }
    } else {
        log_write('Task ' . $name . ' not scheduled because ' . $os_name . ' ' . $os_ver . ' is not supported');
    }

    //* DEBUG
    if (($task != "") and system($task, $return)) {
        log_write("Task $name scheduled to $sch_date $sch_time");
    } else {
        log_write("Task $name NOT scheduled: $return");
		log_write('Command: ' . $task);
    }
     //*/

    return $return;
}

function delete_task($name) {
	$task = sprintf('schtasks /delete /tn %s /F', $name);
	if (system($task, $return)) {
        log_write("Task $name deleted");
	} else {
        log_write("Task $name NOT deleted: $return");
	}
	return $return;
}

function exit_script($str) {
    log_write($str);
    // new attempt in 5 minutes
	$t = time() + 5*60;
    schedule_task($GLOBALS['task_names'] . 'safe', $t);
    log_write("Script ends\r\n---------------------------------------");
    exit;
}

?>
