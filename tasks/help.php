<?php

if ($handle = opendir($tasksDir)) {
  while (false !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != "..") {
      echo str_replace(".php","",$entry)."\n";
    }
  }

  closedir($handle);
}