<?php
/*
 * Ce script peut être appelé en local ou à distance via http
 */
require("vendor/autoload.php");

use Symfony\Component\Yaml\Yaml;

$rootDir = __DIR__;
$rootName = basename(realpath(__DIR__));
$projectRootDir = realpath(__DIR__."/../");
$tasksDir = $rootDir.'/tasks';
$tmpDirectoryName = "data";
$tmpDirectory = $rootDir.'/'.$tmpDirectoryName;
checkDir($tmpDirectory);

$config = Yaml::parse($rootDir."/config.yml");

if ($config["prod_server"]["settings_file"]["type"] == "php") {
	require($projectRootDir."/".$config["prod_server"]["settings_file"]["path"]);
}
// var_dump($config);

define("_CONST_ALLOWED_DAEMON_CLIENT",$config["prod_server"]["trusted_ips"]);
define("_CONST_PROD_DOMAIN",$config["prod_server"]["host"]);


error_reporting(E_ERROR);

if(file_exists("/etc/apache2/envvars")){
    $apacheRunUser = trim(shell_exec("cat /etc/apache2/envvars | grep APACHE_RUN_USER | sed -e 's/.*=//'"));
    $apacheRunGroup = trim(shell_exec("cat /etc/apache2/envvars | grep APACHE_RUN_GROUP | sed -e 's/.*=//'"));
}


if(isset($_GET["run"])){
    $execMode = "web";
    $outpuStr = "<strong>%s</strong><br>";
    $outpuStrRes = "<strong>%s</strong><br>";
    //$outpuStr
    $runCommands = explode(" ",$_GET["run"]);
    $args1 = $runCommands[0];
}
elseif(count($_SERVER['argv']) > 1){
    $execMode = "console";
    $outpuStr = "\033[0;33m%s\033[0m\n";
    $outpuStrRes = "\033[0;34m%s\033[0m\n";
    $args1 = $_SERVER['argv'][1];
}
if(count($_SERVER['argv']) <= 1 && !$_GET["run"])
{
  echo "No task selected\n";
  exit(1);
}

$task = sprintf('%s/%s', $tasksDir, $args1);
$task .= ".php";
if(!is_file($task))
{
  echo "Unknown task : $task\n";
  exit(1);
}

if(isset($_GET["run"]) && isAllowedClient()){
    $runCommands = explode(" ",$_GET["run"]);
    $params = array_slice($runCommands, 1);
}
else{
    $params = array_slice($_SERVER['argv'], 2);
}

$status = 0;

require_once($task);

exit($status);

function taskExecute($cmd,$notice = "")
{
  global $outpuStr; global $outpuStrRes;
  $results = array();
  $status = 0;

  printf($outpuStr, $notice);
  exec($cmd, $results, $status);

  foreach ($results as  $line) {
    printf($outpuStrRes, $line);
  }

  return $status;
}
function checkDir($dirPath,$grant="is_writable"){
    if(function_exists($grant)){
        if(file_exists($dirPath)){
            if(! $grant($dirPath)){
                printf("\033[0;33m%s\033[0m\n", "Grant misfit : ".$dirPath." failed the test ".$grant);
            }
        }
        else{
			if(!mkdir($dirPath)){
				printf("\033[0;33m%s\033[0m\n", "Creation of directory ".$dirPath." failed");
			}
			else{
				printf("\033[0;33m%s\033[0m\n", "Directory ".$dirPath." created");
			}
        }
    }
    else{
        printf("\033[0;33m%s\033[0m\n", "Unknown function ".$grant);
    }
}
/*
 * Teste l'ip cliente pour être sûr que le script est lancé à partir du serveur (cron) ou par un navigateur de c2is
 */
function isAllowedClient(){
    $allowedIps = explode(",",_CONST_ALLOWED_DAEMON_CLIENT); // c2is
    if(in_array(get_ip_address(),$allowedIps)){
        return true;
    }
    else {
        echo "Untrusted client";
        return false;
    }
}

function get_ip_address() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }
    }
}
