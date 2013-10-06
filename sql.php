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
        $filePath = sprintf('%s/sql/%s.sql.gz', $tmpDirectory, $bdd_name);

        // here we do not use shell command because of settings which can forbid it on the remote server
        if ($bdd_password != "") {
            file_put_contents($filePath, gzencode(mysqldump($bdd_host, $bdd_user, $bdd_name), 9));
        } else {
            file_put_contents($filePath, gzencode(mysqldump($bdd_host, $bdd_user, $bdd_password, $bdd_name), 9));
        }

        // don't use readfile because of memory limitation under webserver (apache...)
        header("Location: http://"._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"]."/".$rootName."/".$tmpDirectoryName."/sql/".$bdd_name.".sql.gz");
    break;

  case 'prod_import':
        $confirm = ask(sprintf("Production database will be imported in %s -> %s, continue ? [y]: ", $bdd_host, $bdd_name), true);
        if($confirm != "y") {
            echo "exiting...";
            exit;
        }

        checkDir($tmpDirectory."/sql/");

        // backups local database
        if ($bdd_password != "") {
             $cmd = sprintf('mysqldump -h%s -u%s -p%s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_password, $bdd_name, $tmpDirectory, $bdd_name.date("Y-m-d--H-i"));
        } else {
             $cmd = sprintf('mysqldump -h%s -u%s %s | gzip -9 > %s/sql/%s.sql.gz', $bdd_host, $bdd_user, $bdd_name, $tmpDirectory, $bdd_name.date("Y-m-d--H-i"));
        }


        $status = taskExecute($cmd,"Backup de la BDD locale...");

        // import production database
        $cmd = sprintf('wget "http://'._CONST_PROD_DOMAIN.$config["prod_server"]["http_path"].'/'.$rootName.'/task.php?run=sql get_prod_export" -O %s/sql/prod.sql.gz',$tmpDirectory);
        $status = taskExecute($cmd,"Récupération de la BDD de prod...");

        // backups local database
        if ($bdd_password != "") {
          $cmd = sprintf('gunzip < %s/sql/prod.sql.gz | mysql -h %s -u %s -p%s %s', $tmpDirectory, $bdd_host, $bdd_user, $bdd_password, $bdd_user);
        } else {
          $cmd = sprintf('gunzip < %s/sql/prod.sql.gz | mysql -h %s -u %s %s', $tmpDirectory, $bdd_host, $bdd_user, $bdd_name);
        }

        $status = taskExecute($cmd,"Import de la BDD de prod dans la bd Courante...");

		// duplicate production database adding current date
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






function mysqldump()
{

    $args = func_get_args();

    $host = $args[0];
    $user = $args[1];

    if (count($args) < 4) {
        $passwd = "";
        $dbName = $args[2];
    } else {
        $passwd = $args[2];
        $dbName = $args[3];
    }
    return _mysqldump($host,$dbName,$user,$passwd);

}


function _mysqldump($host,$dbName,$user,$passwd){
    var_dump(func_get_args());
    $content = "";
    if ($passwd == "") {
        $linker = mysql_connect($host, $user) or die("Connection failed");
    } else {
        $linker = mysql_connect($host, $user, $passwd) or die("Connection failed");
    }


    mysql_select_db($dbName, $linker);
    $sql="show tables;";
    $result= mysql_query($sql);

    if( $result)
    {
        while( $row= mysql_fetch_row($result))
        {
            $content .= _mysqldump_table_structure($row[0]);
            $content .= _mysqldump_table_data($row[0]);
        }
    }
    mysql_free_result($result);
    return $content;
}

function _mysqldump_table_structure($table)
{
    $content = "";
    $content .= "/* Table structure for table `$table` */\n";
    $content .= "DROP TABLE IF EXISTS `$table`;\n\n";
    $sql="show create table `$table`; ";
    $result=mysql_query($sql);
    if( $result)
    {
        if($row= mysql_fetch_assoc($result))
        {
            $content .= $row['Create Table'].";\n\n";
        }
    }
    mysql_free_result($result);
    return $content;
}

function _mysqldump_table_data($table)
{
    $content = "";
    $sql="select * from `$table`;";
    $result=mysql_query($sql);
    if( $result)
    {
        $num_rows= mysql_num_rows($result);
        $num_fields= mysql_num_fields($result);

        if( $num_rows > 0)
        {
            $content .= "/* Dumping data for table `".$table."` */\n";

            $field_type=array();
            $i=0;
            while( $i < $num_fields)
            {
                $meta= mysql_fetch_field($result, $i);
                array_push($field_type, $meta->type);
                $i++;
            }

            $content .= "insert into `".$table."` values\n";
            $index=0;
            while( $row= mysql_fetch_row($result))
            {
                $content .= "(";
                for( $i=0; $i < $num_fields; $i++)
                {
                    if( is_null( $row[$i]))
                        $content .= "null";
                    else
                    {
                        switch( $field_type[$i])
                        {
                            case 'int':
                                $content .= $row[$i];
                                break;
                            case 'string':
                            case 'blob' :
                            default:
                            $content .= "'".mysql_real_escape_string($row[$i])."'";

                        }
                    }
                    if( $i < $num_fields-1)
                        $content .= ",";
                }
                $content .= ")";

                if( $index < $num_rows-1)
                    $content .= ",";
                else
                    $content .= ";";
                $content .= "\n";

                $index++;
            }
        }
    }
    mysql_free_result($result);
    $content .= "\n";
    return $content;
}
