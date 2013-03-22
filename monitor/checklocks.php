<?php

const LOG_DIR = '../log';
const WARNING_HOURS = 2;

header('Content-type: text/plain');

$dir = dir(LOG_DIR);
$lockCount = 0;

while ($entry = $dir->read()) {
	if (preg_match('/(.+)\.lock\.last$/', $entry, $matches)) {
		$lockCount++;
		if (filemtime($dir->path . '/' . $entry) < time() - WARNING_HOURS * 3600) {
			echo "Last $matches[1] process completed more than " . WARNING_HOURS . " hours ago at " .
					date('Y-m-d H:i:s', filemtime($dir->path . '/' . $entry)) . "\n";
		}
	}
}

if (!$lockCount) {
	echo 'No lock files found';
}