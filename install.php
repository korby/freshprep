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
if (! in_array("ftp", $modsAvailable)) {
    echo "Module tp missing, please install it";
    exit;
}


$strProtocol = "sftp";
$strProtocolGiven  = ask(sprintf('Enter the transfert protocol, sftp or ftp (default %s):', $strProtocol), true, "`(sftp|ftp)`");
$strProtocol = ($strProtocolGiven != "")? $strProtocolGiven : $strProtocol;
if($strProtocol == "sftp") {
    $strServerPort = "22";
} else if($strProtocol == "ftp") {
    $strServerPort = "21";
}
$strServer = ask('Enter the remote '.$strProtocol.' host:', false, array(FILTER_VALIDATE_IP, "`[a-z]+\.[a-z]*$`"), "or");
$strServerPortGiven = ask(sprintf('Enter the remote '.$strProtocol.' port (default %s):', $strServerPort), true, "`^[0-9]*$`");
$strServerPort = ($strServerPortGiven != "")? $strServerPortGiven : $strServerPort;
$strServerRootPath = ask('Enter the remote root path (/var/www/myproject/ for example):', false, "`^/(.*/)*$`");
$strServerUsername = ask('Enter the '.$strProtocol.' user name:');
$strServerPassword = ask('Enter the '.$strProtocol.' user password:');
$strServerRootPath .= "freshprep/";

echo "\nConnecting to server...";
$stream = connectAndAuth();

echo "\nBeginning file transfer to server...";
dirmk($stream, "");
$ignore = array(".git", ".idea", "data");
if (strpos($rootDir,"freshprep") === false) {
    echo "Path problem, no 'freshprep' occurence within... exiting.";
    exit;
}
chdir($rootDir);

uploadDir(".", $stream);
echo "\n";

function connectAndAuth() {
    global $strProtocol;
    global $strServer;
    global $strServerPort;
    global $strServerUsername;
    global $strServerPassword;

    if($strProtocol == "sftp") {
        $callbacks = array('disconnect' => 'ssh_disconnect');
        $resConnection = ssh2_connect($strServer, $strServerPort, null, $callbacks);

        if(ssh2_auth_password($resConnection, $strServerUsername, $strServerPassword)){
            //Initialize SFTP subsystem
            $stream = ssh2_sftp($resConnection);
        } else{
            echo "Unable to authenticate on server";
            exit;
        }
    } elseif($strProtocol == "ftp") {
        $stream =  ftp_connect($strServer, $strServerPort);
        if(! ftp_login($stream, $strServerUsername, $strServerPassword)) {
            echo "Unable to authenticate on server";
            exit;
        }
    }

    return $stream;
}
function uploadDir($dirPath, $stream)
{
    global $ignore;
    global $rootDir;
    $buffer = opendir($dirPath) or die("Erreur le repertoire $dirPath existe pas");
    while($fichier = @readdir($buffer))
    {
        // enlever les traitements inutile
        if ($fichier == "." || $fichier == ".." || in_array($fichier,$ignore)) continue;

        if(is_dir($dirPath.'/'.$fichier))
        {
            if (!dirmk($stream, $dirPath.'/'.$fichier)) {
                distError();
            }
            uploadDir($dirPath.'/'.$fichier, $stream);
        }
        else
        {
            echo "\nSending ".$rootDir."/".$dirPath."/".$fichier;
            if (!put($stream, $dirPath."/".$fichier)) {
                distError();
            }
        }

    }

    closedir($buffer);
}

function get($stream, $RemoteFilePath) {
    global $strProtocol;
    return file_get_contents("ssh2.sftp://{$stream}{$RemoteFilePath}", 'r');
}

function put($stream, $filePath) {
    global $strProtocol;
    global $rootDir;
    global $strServerRootPath;

    if($strProtocol == "sftp") {
        return file_put_contents("ssh2.sftp://{$stream}{$strServerRootPath}{$filePath}", file_get_contents($rootDir."/".$filePath));
    } elseif($strProtocol == "ftp") {
        return ftp_fput($stream, $strServerRootPath.$filePath, fopen($rootDir."/".$filePath, "r"), FTP_BINARY);
    }


}
function dirmk($stream, $dirPath) {
    global $strProtocol;
    global $strServerRootPath;
    echo "\nCreating directory ".$strServerRootPath.$dirPath;

    if($strProtocol == "sftp") {
        ssh2_sftp_mkdir($stream, $strServerRootPath.$dirPath, 0777);

        return ssh2_sftp_chmod ( $stream , $strServerRootPath.$dirPath, 0777 );
    } elseif($strProtocol == "ftp") {
        ftp_mkdir($stream, $strServerRootPath.$dirPath);

        return ftp_chmod($stream, 0777, $strServerRootPath.$dirPath);
    }

}



function distError() {
    echo "Action failed, process will be killed\n";
    echo "Are you sure the protocol and auth are the rights ?\n";
    die();
}
function ssh_disconnect($reason, $message) {
    printf("Server disconnected with reason code [%d] and message: %s\n",
        $reason, $message);
}

function help()
{
    echo "No argument needed for this command. It installs freshprep on the remote production server\n";
    echo "You will be prompted to give sFtp's connection parameters\n";
}
