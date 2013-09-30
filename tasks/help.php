<?php

$currentWd = trim(shell_exec("pwd"));

if ($handle = opendir($tasksDir)) {
  while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != "..") {
      echo $currentWd."/task ".str_replace(".php","",$entry)." help\n";
    }
  }

  closedir($handle);
}