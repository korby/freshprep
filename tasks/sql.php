<?php
foreach ($config["prod_server"]["bdd"] as $var=>$arr) {
	switch($arr["type"]) {
		case "const":
			$$var = constant($arr["name"]);
			break;
		case "var":
			$$var = $$arr["name"];
			break;
	}
}


if(!count($params))
{
    help();
    exit(1);
}


switch($params[0])
{
  case 'export':
        checkDir($tmpDirectory."/sql/");
	  	if ($bdd_password != "") {
		  $cmd = sprintf('mysqldump -h %s -u %s -p%s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_password, $bdd_name, $tmpDirectory, $bdd_name);
	  	} else {
			$cmd = sprintf('mysqldump -h %s -u %s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_name, $tmpDirectory, $bdd_name);
		}

        $status = taskExecute($cmd);

  break;
    case 'get_prod_export':
        checkDir($tmpDirectory."/sql/");
        if ($bdd_password != "") {
            $cmd = sprintf('mysqldump -h %s -u %s -p%s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_password, $bdd_name, $tmpDirectory, $bdd_name);
        } else {
            $cmd = sprintf('mysqldump -h %s -u %s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_name, $tmpDirectory, $bdd_name);
        }

        $status = shell_exec($cmd);
         // on ne passe pas par un readfile pour retourner les data à cause des limitations de mémoire de php sous apache
        header("Location: http://"._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName."/".$tmpDirectoryName."/sql/".$bdd_name.".sql.gz");
    break;

  case 'prod_import':
        // on backup la bdd locale
        $cmd = sprintf('mysqldump -h%s -u%s -p%s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_password, $bdd_name, $tmpDirectory, $bdd_name.date("Y-m-d--H-i"));
        $status = taskExecute($cmd,"Backup de la BDD locale...");

        // on importe celle de prod
        $cmd = sprintf('wget "http://'._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"].'/'.$rootName.'/task.php?run=sql get_prod_export" -O %s/sql/prod.sql.gz',$tmpDirectory);
        $status = taskExecute($cmd,"Récupération de la BDD de prod...");

        $cmd = sprintf('gunzip < %s/sql/prod.sql.gz | /usr/bin/mysql -h %s -u %s -p%s %s', $tmpDirectory, $bdd_host, $bdd_user, $bdd_password, $bdd_name);
        $status = taskExecute($cmd,"Import de la BDD de prod dans la bd Courante...");

		// on duplique la bdd de prod en spécifiant la date
        $cmd = sprintf('cp %s/sql/prod.sql.gz %s/sql/prod-'.date("Y-m-d--H-i").'.sql.gz ',$tmpDirectory, $tmpDirectory);
        $status = taskExecute($cmd,"Duplication du dump de la BDD de prod en spécifiant la date...");

    break;

  case 'help':
  default:
    help();
}


function help()
{
	global $tmpDirectory;
    global $config;
    global $rootName;
    echo "Use 'export', 'get_prod_export' or 'prod_import' argument.\n";
    echo "'export' exports the local database of the current project (*sql.gz) into the ".$tmpDirectory." directory.\n";
    echo "'get_prod_export' do the export on the production and gives you the file. It must be called via http, not in console mode. To call it : http://"._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName."/task.php?run=sql get_prod_export.\n";
    echo "'prod_import' automatically get the database export and deploy it into you project local database.\n";
}
