<?php
spl_autoload_register(function ($class_name) {

    $dirs = glob("*", GLOB_ONLYDIR);

    foreach ($dirs as $dir){

        if(file_exists($dir.DS.$class_name . '.php')){
            include $class_name . '.php';
        }else{
            die();
        }
    }


});