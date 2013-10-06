<?php

if($params[0] == "help")
{
    help();
    exit;
}

echo "Freshprep is here";

function help(){
    echo "Just return text \"Freshprep is here\", used by freshprep to test remote existence ";
}
