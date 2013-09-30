<?php

if($params[0] == "help")
{
    help();
    exit;
}

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
$strServer = ask('Enter the remote sftp host:', false, array(FILTER_VALIDATE_IP, "`[a-z]+\.[a-z]*$`"), "or");
$strServerPortGiven = ask(sprintf('Enter the remote sftp port (default %s):', $strServerPort), true, "`^[0-9]*$`");
$strServerPort = ($strServerPortGiven != "")? $strServerPortGiven : $strServerPort;
$strServerRootPath = ask('Enter the remote root path (/var/www/myproject/ for example):', false, "`^/(.*/)*$`");
$strServerUsername = ask('Enter the sftp user name:');
$strServerPassword = ask('Enter the sftp user password:');
$strServerRootPath .= "freshprep/";

echo "\nConnecting to server...";
$resConnection = ssh2_connect($strServer, $strServerPort);

if(ssh2_auth_password($resConnection, $strServerUsername, $strServerPassword)){
    //Initialize SFTP subsystem
    $sftpStream = ssh2_sftp($resConnection);
} else{
    echo "Unable to authenticate on server";
    exit;
}

echo "\nBeginning file transfer to server...";
dirmk($sftpStream, "");
$ignore = array(".git", ".idea");
chdir($rootDir);
uploadDir(".", $sftpStream);
echo "\n";


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
            dirmk($sftpStream, $dirPath.'/'.$fichier);
            uploadDir($dirPath.'/'.$fichier, $sftpStream);
        }
        else
        {
            put($sftpStream, $dirPath."/".$fichier);
        }



    }

    closedir($buffer);
}

function help()
{
    echo "No argument needed for this command. It installs freshprep on the remote production server\n";
    echo "You will be prompted to give sFtp's connection parameters\n";
}
