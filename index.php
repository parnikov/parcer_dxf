<?php

    require __DIR__ . DIRECTORY_SEPARATOR . "sources" .DIRECTORY_SEPARATOR ."settings.php";
    if($_POST) require __DIR__ . "sources".DS. "classes".DS."__autoload.php";
?><!doctype html>
<html lang="ru">
<head>
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
    $handle = fopen("demo/demo.dxf", "r");
    $flag=false;
    $arrStructure=array();
    if ($handle) {

        // получение всех элементов в слое
        while (($buffer = fgets($handle, 4096)) !== false) {
            if( trim($buffer) == "ENTITIES" ){
                $flag = true;
            }elseif ( $flag == true && trim($buffer) !== "" ){
                if( preg_match('#[A-Z]{3,}#i', $buffer) ){
                    if(isset($figure) && $figure["VALUES"])
                        $arrStructure[] = $figure;
                    $figure = array("NAME"=>trim($buffer), "VALUES"=>array());

                }elseif($buffer && isset($figure)){
                    $figure["VALUES"][] = trim($buffer);
                }
            }
        }
        $figures=array();
        $width = 0;
        $height = 0;
        $offsetY = 10;
        $offsetX = 10;
        /**
         * Функция опредяет размеры полотна для отрисовки
         * @param array $arrCoordinate
         */
        function dimensioning($arrCoordinate=array(), $line=false){

            if( ! $arrCoordinate) return;

            global $width,$height, $offsetX, $offsetY;
            if( $arrCoordinate[5] < 0 && abs( $arrCoordinate[5] ) > $offsetX )
                $offsetX =  abs( $arrCoordinate[5] );

            if( $arrCoordinate[6] < 0 && abs( $arrCoordinate[6] ) > $offsetY )
                $offsetY =  abs( $arrCoordinate[6] );

            if( $width < $arrCoordinate[5] )
                $width = $arrCoordinate[5]+400;

            if( $height < $arrCoordinate[7] )
                $height = $arrCoordinate[7];

            if($line){

                if( $arrCoordinate[11] < 0 && abs( $arrCoordinate[11] ) > $offsetX )
                    $offsetX =  abs( $arrCoordinate[11] );

                if( $arrCoordinate[13] < 0 && abs( $arrCoordinate[13] ) > $offsetY )
                    $offsetY =  abs( $arrCoordinate[13] );

                if( $width < $arrCoordinate[11] )
                    $width = $arrCoordinate[11];

                if( $height < $arrCoordinate[13] )
                    $height = $arrCoordinate[13];

            }

        }

        // компоновка данных по фигурам
        $length = count($arrStructure);
        $figureCnt=0;

        for( $i=0; $i<$length; $i++ ){

            switch($arrStructure[$i]["NAME"]){
                // собираем вершины у полилинии
                case "POLYLINE":
                    $figures[$figureCnt]=array("NAME"=>$arrStructure[$i]["NAME"], "POINTS"=>array());
                    for($a=$i+1;;$a++){
                        if($arrStructure[$a]["NAME"] != "VERTEX")
                            break;
                        dimensioning($arrStructure[$a]["VALUES"]);
                        $figures[$figureCnt]["POINTS"][] = array("X"=>$arrStructure[$a]["VALUES"][5],
                                                                 "Y"=>$arrStructure[$a]["VALUES"][7]);
                    }
                    $i=$a;
                    $figureCnt++;
                    break;
                case "ARC":
                    $figures[$figureCnt]["START_ANGLE"] = $arrStructure[$i]["VALUES"][13];
                    $figures[$figureCnt]["END_ANGLE"] = $arrStructure[$i]["VALUES"][15];
                case "CIRCLE":
                    $figures[$figureCnt]["RADIUS"]=$arrStructure[$i]["VALUES"][11];
                    $figures[$figureCnt]["NAME"]=$arrStructure[$i]["NAME"];
                    dimensioning($arrStructure[$i]["VALUES"]);
                    $figures[$figureCnt]["POINTS"][]=array("X"=>$arrStructure[$i]["VALUES"][5],
                                                            "Y"=>$arrStructure[$i]["VALUES"][7]);

                    $figureCnt++;
                    break;
                case "LINE":
                    $figures[$figureCnt]["NAME"]=$arrStructure[$i]["NAME"];
                    dimensioning($arrStructure[$i]["VALUES"], true);
                    $figures[$figureCnt]["POINTS"][]=array("X"=>$arrStructure[$i]["VALUES"][5],
                                                            "Y"=>$arrStructure[$i]["VALUES"][7]);
                    $figures[$figureCnt]["POINTS"][]=array( "X"=>$arrStructure[$i]["VALUES"][11],
                                                            "Y"=>$arrStructure[$i]["VALUES"][13]);
                    $figureCnt++;
                    break;
                default:
                    break;
            }
        }

        $paddingX = $width*0.05;
        $paddingY = $height*0.05;

        $offsetX += $paddingX;
        $offsetY += $paddingY;

        // создаем изображение, на котором будем рисовать
        $img = imagecreatetruecolor($width+$offsetX+$paddingX, $height+$offsetY+$paddingY);
        // создаем цвета
        $red   = imagecolorallocate($img, 255, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        // цвет заливки фона
        $rgb = 0x000000;
        // заливаем холст цветом $rgb
        imagefill($img, 0, 0, $rgb);

        foreach ( $figures as $figure ){


            switch($figure["NAME"]){

                case "LINE":
                    $x1 = $figure["POINTS"][0]["X"] + $offsetX;
                    $y1 = $figure["POINTS"][0]["Y"] + $offsetY;
                    $x2 = $figure["POINTS"][1]["X"] + $offsetX;
                    $y2 = $figure["POINTS"][1]["Y"] + $offsetY;
                    imageline ($img, $x1, $y1, $x2, $y2, $white);
                    break;
                case "POLYLINE":

                    $length = count($figure["POINTS"]);
                    for( $i = 0; $i < $length; $i++ ){
                        // координаты линии
                        if(isset($figure["POINTS"][$i+1]["Y"])){
                            $x1 = $figure["POINTS"][$i]["X"] + $offsetX;
                            $y1 = $figure["POINTS"][$i]["Y"] + $offsetY;
                            $x2 = $figure["POINTS"][$i+1]["X"] + $offsetX;
                            $y2 = $figure["POINTS"][$i+1]["Y"] + $offsetY;
                        // замыкаем
                        }else{
                            $x1 = $figure["POINTS"][$i]["X"] + $offsetX;
                            $y1 = $figure["POINTS"][$i]["Y"] + $offsetY;
                            $x2 = $figure["POINTS"][0]["X"] + $offsetX;
                            $y2 = $figure["POINTS"][0]["Y"] + $offsetY;
                        }
                        imageline ($img, $x1, $y1, $x2, $y2, $white);
                    }

                    break;

                case "CIRCLE":
                    imagearc(   $img,
                            $figure["POINTS"][0]["X"] + $offsetX,
                            $figure["POINTS"][0]["Y"] + $offsetY,
                            $figure["RADIUS"]*2,
                            $figure["RADIUS"]*2,
                            0, 360, $white);
                    break;
                case "ARC":
                    imagearc(   $img,
                            $figure["POINTS"][0]["X"] + $offsetX,
                            $figure["POINTS"][0]["Y"] + $offsetY,
                            $figure["RADIUS"]*2, $figure["RADIUS"]*2,
                            $figure["START_ANGLE"], $figure["END_ANGLE"], $white);

                    break;

            }

        }
        ob_start ();

        imageflip($img, IMG_FLIP_VERTICAL);
        imagejpeg ($img);
        $image_data = ob_get_contents ();
        ob_end_clean ();
        imagedestroy($img);
        $image_data_base64 = base64_encode ($image_data);

        echo "<img width='100%' src='data:image/png;base64,$image_data_base64'>";
    }
    ?>
</div>
</body>
</html>