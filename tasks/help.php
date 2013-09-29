<?php

if ($handle = opendir($tasksDir)) {
  while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != "..") {
      echo "./task ".str_replace(".php","",$entry)." help\n";
    }
  }

  closedir($handle);
}