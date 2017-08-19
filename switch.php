<?php 
error_reporting(1);//07572133848 oliva akankwasibwa

$xml = simplexml_load_file("conf/config.xml") or die("Error: Cannot create object");

ini_set('display_errors' , $xml->server->displayerrors);
ini_set('date.timezone' , $xml->server->timezone);
ini_set('session.gc_maxlifetime' , $xml->server->maxlifetime);
set_time_limit($xml->server->timelimit);
date_default_timezone_set($xml->server->timezone);

require_once('loging.php');
require_once('conf/db.class.php');
require_once 'lib/PasswordLib/bootstrap.php';

$file_time = date("Y-m-d H");
$log_file = "logs/$file_time-switch.log";
openLogFile($log_file);
writeToLogFile("---- New Request ----\n");

$result = array();

$request = $_REQUEST['request'];
$params = (array)$request;
//$params = array('user_id'=>'3','branch_id'=>5,'session_id'=>'','controller'=>'transfers','action'=>'getbalances');

$controller = ucfirst(strtolower($params['controller']));
writeToLogFile("Controller : $controller\n");
$action = strtolower($params['action']).'Action';
writeToLogFile("Method : $action\n");

if( file_exists("controllers/{$controller}.class.php") ){
	writeToLogFile("Controller File : controllers/{$controller}.class.php, Exists\n");
	include_once "controllers/{$controller}.class.php";
}else{
	writeToLogFile("Controller file doesn't Exist\n");
	$result['success'] = 0;
	$result['errormsg'] = "Unknown request method";	
	
	reply($result); 
}

$db = new DB($xml);
$controller = new $controller($params,$db);

if(method_exists($controller, $action) === false ){
	writeToLogFile("Request method does not match controller\n");
	$result['success'] = 0;
	$result['errormsg'] = "Bad request";
	
	reply($result); 
}

$result=array();
$result = $controller->$action();
writeToLogFile("Response : ".json_encode($result)."\n");
reply($result);

//close all objects
$db.closeDB();
closeLogFile();


function reply($result){
	header('Cache-Control: no-cache, must-revalidate');
	header('Content-type: application/javascript; charset=utf-8');
	$result = json_encode($result);
	if(!isset($_GET['callback'])){
		exit( "$result" );
	}

	if(is_valid_callback($_GET['callback'])){
		exit("{$_GET['callback']}('$result')");
	}

	header('Status: 400 Bad Request', true, 400);
}

function is_valid_callback($subject){
	$identifier_syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

	$reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
		'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
		'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
		'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
		'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
		'private', 'public', 'yield', 'interface', 'package', 'protected', 
		'static', 'null', 'true', 'false');

	return preg_match($identifier_syntax, $subject) && ! in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
}

?>