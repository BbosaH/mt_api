<?php

// DEBUG flag
// Enables writing to log file for debug
// Must be turned off in production coz
// of multiple users!!
$DEBUG = 1;

//
// Logging wrappers
//
$fileHandle = NULL;
function openLogFile($fileName) {
	global $fileHandle;
	global $DEBUG;

	if ($DEBUG) {
		$fileHandle = fopen($fileName,"a");
	}
}
function writeToLogFile($txt) {
	global $fileHandle;
	global $DEBUG;

	if ($DEBUG ) {
		// logging
		$logTime = date("Y-m-d H:i:s");
		$txt = "$logTime $txt";

		if ($fileHandle) {
			fwrite($fileHandle,$txt);
		}
	}
}

function closeLogFile() {
	global $fileHandle;
	global $DEBUG;

	if ($DEBUG ) {
		if ($fileHandle) {
			fclose($fileHandle);
		}
	}
}

?>