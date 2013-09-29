<?php

$modsAvailable = explode("\n", shell_exec("php -m"));
if (! in_array("openssl", $modsAvailable)) {
    echo "Module openssl missing, please install it";
    exit;
}
if (! in_array("ssh2", $modsAvailable)) {
    echo "Module openssl missing, please install it";
    exit;
}

$strServerPort = "22";


echo 'Enter the remote sftp host:';
$strServer = stream_get_line(STDIN, 1024, PHP_EOL);

printf('Enter the remote sftp port (default %s):', $strServerPort);
$strServerPortGiven = stream_get_line(STDIN, 1024, PHP_EOL);
$strServerPort = ($strServerPortGiven != "")?$strServerPortGiven:$strServerPort;

echo 'Enter the remote root path (/var/www/myproject/ for example):';
$strServerRootPath = stream_get_line(STDIN, 1024, PHP_EOL);

echo 'Enter the sftp user name:';
$strServerUsername = stream_get_line(STDIN, 1024, PHP_EOL);

echo 'Enter the sftp user password:';
$strServerPassword = stream_get_line(STDIN, 1024, PHP_EOL);

//connect to server
$resConnection = ssh2_connect($strServer, $strServerPort);

if(ssh2_auth_password($resConnection, $strServerUsername, $strServerPassword)){
    //Initialize SFTP subsystem
    $sftpStream = ssh2_sftp($resConnection);
} else{
    echo "Unable to authenticate on server";
    exit;
}

dirmk($sftpStream, "");
$ignore = array(".git", ".idea");
uploadDir(".", $sftpStream);


/*
put($sftpStream, "task.php");
put($sftpStream, "config.yml");
dirmk($sftpStream, "data");
*/

function get($sftpStream, $RemoteFilePath) {
    return file_get_contents("ssh2.sftp://{$sftpStream}{$RemoteFilePath}", 'r');
}

function put($sftpStream, $filePath) {
    global $rootDir;
    global $strServerRootPath;
    echo "\nSending {$rootDir}"/"{$filePath} to {$rootDir}{$filePath}";

    return file_put_contents("ssh2.sftp://{$sftpStream}{$strServerRootPath}{$filePath}", $rootDir."/".file_get_contents($filePath));
}
function dirmk($sftpStream, $dirPath) {
    global $strServerRootPath;
    echo "\nCreating directory ".$strServerRootPath.$dirPath;
    ssh2_sftp_mkdir($sftpStream, $strServerRootPath.$dirPath, 0777);
    return ssh2_sftp_chmod ( $sftpStream , $strServerRootPath.$dirPath, 0777 );
}


function uploadDir($dirPath, $sftpStream)
{
    global $ignore;
    $buffer = opendir($dirPath) or die("Erreur le repertoire $dirPath existe pas");
    while($fichier = @readdir($buffer))
    {
        // enlever les traitements inutile
        if ($fichier == "." || $fichier == ".." || in_array($fichier,$ignore)) continue;

        if(is_dir($dirPath.'/'.$fichier))
        {
            print "\n".$dirPath."/".$fichier;
            dirmk($sftpStream, $dirPath.'/'.$fichier);
            uploadDir($dirPath.'/'.$fichier, $sftpStream);
        }
        else
        {
            echo "\nSending ".$dirPath."/".$fichier;
            put($sftpStream, $dirPath."/".$fichier);
        }



    }

    closedir($buffer);
}

