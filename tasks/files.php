<?php

if(!count($params))
{
    help();
    exit(1);
}

switch($params[0])
{
    case 'get_prod_export':
        $filesLocations = implode(" ",$config["prod_server"]["files"]);
        $tgzFilePath = $tmpDirectory."/files.tgz";
        $cmd[] = "cd ".$projectRootDir;
        $cmd[] = sprintf('tar -zcf %s %s', $tgzFilePath, $filesLocations);
        $status = shell_exec(implode(";", $cmd));
        // on ne passe pas par un readfile pour retourner les data à cause des limitations de mémoire de php sous apache
        header("Location: http://"._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName."/".$tmpDirectoryName."/files.tgz");
    break;
    case 'prod_import':
        $tgzFilePath = $tmpDirectory."/files.tgz";
        $filesLocations = implode(" ",$config["prod_server"]["files"]);

        $cmd = sprintf('wget "http://'._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName.'/task.php?run=files get_prod_export" -O %s',$tgzFilePath);
        $status = taskExecute($cmd,"Downloading from production tgz archive of files ".$filesLocations);
        echo "\nExpanding archive...";
        $cmd[] = "cd ".$projectRootDir;
        $cmd[] = sprintf('tar -zxf %s',$tgzFilePath);
        $status = shell_exec(implode(";", $cmd));

    break;

  case 'help':
  default:
    help();
}


function help()
{
  global $config;
  global $rootName;
  echo "Use 'get_prod_export' or 'get_import' argument. \n";
  echo "'get_prod_export' give you a tarball shared files and must be called via http, not in console mode. To call it : http://"._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName."/task.php?run=files get_prod_export. \n";
  echo "'get_import' automatically get the tarball of shared files and expand it into your local project. \n";

}
