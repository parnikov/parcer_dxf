<?php
    require __DIR__ . DIRECTORY_SEPARATOR . "sources" .DIRECTORY_SEPARATOR ."settings.php";
    if($_POST) require __DIR__ . "sources".DS. "classes".DS."__autoload.php";
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Parser .dxf</title>
    <link rel="stylesheet" href="<?=$_SERVER["SERVER_NAME"]?>">
</head>
<body>
<div class="wrapper">
    <form action="<?=$_SERVER["SERVER_NAME"]?>" method="post" enctype="multipart/form-data">
        <div class="row"><input type="file" name="dxf"></div>
        <div class="row"><input type="submit" value="Отправить"></div>
    </form>
    <?php
        if($_POST){

        }
    ?>
</div>
</body>
</html>