<?php

if(!count($params))
{
  help();
  exit(1);
}

switch($params[0])
{
  case 'export':
    foreach($sites as $siteUrl => $siteDirectory)
    {
      $exportPath = sprintf('%s/struct/%s', $tmpDirectory, $siteDirectory);
      if(!is_dir($exportPath))
      {
        mkdir($exportPath);
      }
      $cmd = sprintf('drush features-export %s  -y -l %s --destination=%s', PROJECT_NAME, $siteUrl, $exportPath);
      $status = taskExecute($cmd);
    }
    break;

  case 'import':
    foreach($sites as $siteUrl => $siteDirectory)
    {
    }
    break;

  case 'help':
  default:
    help();
}


function help()
{
  echo "Use 'export' or 'import' argument.\n";
}