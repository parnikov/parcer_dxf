<?php
header('Content-Type: text/html; charset=utf-8', true);
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
<?php
/**
 * Функция опредяет размеры полотна для отрисовки
 * @param array $arrCoordinate - массив с координатами
 */
function dimensioning( $arrCoordinate=array() ){

    // учитываем корректные данные
    if( ! isset($arrCoordinate[7]) || ! $arrCoordinate ) return;


    global $width,$height,$offsetX,$offsetY;

    // определяем отступ по x для глобального смещения координат
    if( $arrCoordinate[5] < 0 && abs( $arrCoordinate[5] ) > $offsetX )
        $offsetX =  abs( $arrCoordinate[5] );
    // определяем отступ по y для глобального смещения координат
    if( $arrCoordinate[7] < 0 && abs( $arrCoordinate[7] ) > $offsetY )
        $offsetY =  abs( $arrCoordinate[7] );

    // ширина полотна по условию
    if( $width < $arrCoordinate[5] )
        $width = $arrCoordinate[5];
    // высота полотна по условию
    if( $height < $arrCoordinate[7] )
        $height = $arrCoordinate[7];
}
// файл для обработки
$handle = fopen("demo/demo.dxf", "r");
// флаг определения границ фигур
$flag=false;
// черновой вариант структуры фигур
$arrStructure=array();

if ($handle) {
    // получение всех элементов в слое
    while (($buffer = fgets($handle, 4096)) !== false) {
        $buffer = trim($buffer);
        if( $buffer == "ENTITIES" ){
            $flag = true;
        }elseif ( $flag == true && $buffer !== "" ){
            if( preg_match('#[A-Z]{4,}#i', $buffer) || $buffer == "ARC" ){

                if(isset($figure) && $figure["VALUES"])
                    $arrStructure[] = $figure;

                $figure = array("NAME"=>$buffer, "VALUES"=>array());

            }elseif( isset($figure) && $figure ){
                $figure["VALUES"][] = $buffer;
            }
        }
    }
    // структура фигур
    $figures=array();
    // ширина полотна картинки
    $width = 0;
    // высота полотна картинки
    $height = 0;
    // отступ по y, если будет любая фигура в 4-ей четверти, то $offsetY > 0
    $offsetY = 0;
    // отступ по x, если будет любая фигура в 3-ей четверти, то $offsetX > 0
    $offsetX = 0;

    // компоновка данных по фигурам
    $length = count($arrStructure);
    $figureCnt=0;

    for( $i=0; $i<$length; $i++ ){
        // кейсы по типу фигур
        switch($arrStructure[$i]["NAME"]){
            // собираем вершины у полилинии
            case "POLYLINE":
                $figures[$figureCnt]=array(
                        "NAME"=>$arrStructure[$i]["NAME"],
                        "POINTS"=>array(),
                        "CLOSED" => isset($arrStructure[$i]["VALUES"][13]) ? 1 : 0
                );

                for($a=$i+1;;$a++){

                    if( ! isset($arrStructure[$a]["NAME"]) ||
                        $arrStructure[$a]["NAME"] != "VERTEX" ||
                        ! isset($arrStructure[$a]["VALUES"][7])
                    ) break;

                    // считаем полотно
                    dimensioning($arrStructure[$a]["VALUES"]);

                    $figures[$figureCnt]["POINTS"][] = array("X"=>$arrStructure[$a]["VALUES"][5],
                                                             "Y"=>$arrStructure[$a]["VALUES"][7],

                                                            );
                }
                $i=$a;
                $figureCnt++;
                break;
            // окружности
            case "ARC":
                $figures[$figureCnt]["START_ANGLE"] = $arrStructure[$i]["VALUES"][13];
                $figures[$figureCnt]["END_ANGLE"] = $arrStructure[$i]["VALUES"][15];
            case "CIRCLE":
                $figures[$figureCnt]["RADIUS"]=$arrStructure[$i]["VALUES"][11];
                $figures[$figureCnt]["NAME"]=$arrStructure[$i]["NAME"];
                // считаем полотно
                dimensioning($arrStructure[$i]["VALUES"]);
                $figures[$figureCnt]["POINTS"][]=array("X"=>$arrStructure[$i]["VALUES"][5],
                                                        "Y"=>$arrStructure[$i]["VALUES"][7]);
                $figureCnt++;
                break;
            // линия
            case "LINE":
                $figures[$figureCnt]["NAME"]=$arrStructure[$i]["NAME"];

                // считаем полотно у начальной точки
                $arTmp[5] = $arrStructure[$i]["VALUES"][5];
                $arTmp[7] = $arrStructure[$i]["VALUES"][7];
                dimensioning($arTmp);

                // считаем полотно у конечной точки
                $arTmp[5] = $arrStructure[$i]["VALUES"][11];
                $arTmp[7] = $arrStructure[$i]["VALUES"][13];

                // считаем полотно
                dimensioning($arTmp);

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
    if( $figures && $width ){

        // визуальные отступы для полотна
        $paddingX = $width*0.05;
        $paddingY = $height*0.05;
        // добавляем отступы к полотну
        $offsetX += $paddingX;
        $offsetY += $paddingY;
        // создаем изображение, на котором будем рисовать
        $img = imagecreatetruecolor($width+$offsetX+$paddingX, $height+$offsetY+$paddingY);
        // создаем цвета
        $red   = imagecolorallocate($img, 255, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        $yellow = imagecolorallocate($img, 255, 255, 0);

        // длина контуров
        $lengthContour = 0;

        // цвет заливки фона
        $rgb = 0x000000;

        // заливаем холст цветом $rgb
        imagefill($img, 0, 0, $rgb);

        foreach ( $figures as $figure ){
            // кейсы по типу фигуры
            switch($figure["NAME"]){

                case "LINE":

                    $x1 = $figure["POINTS"][0]["X"] + $offsetX;
                    $y1 = $figure["POINTS"][0]["Y"] + $offsetY;
                    $x2 = $figure["POINTS"][1]["X"] + $offsetX;
                    $y2 = $figure["POINTS"][1]["Y"] + $offsetY;

                    imageline ($img, $x1, $y1, $x2, $y2, $yellow);
                    // сумма кватратов разности начальной и конечной точек
                    $sum =  pow($x2 -  $x1, 2 )+ pow($y2 - $y1,2);
                    // длина линии по координатам sqrt($sum)
                    $lengthContour += sqrt($sum);

                    break;

                case "POLYLINE":

                    $length = count($figure["POINTS"]);

                    for( $i = 0; $i < $length; $i++ ){
                        $x1 = 0;
                        $y1 = 0;
                        $x2 = 0;
                        $y2 = 0;
                        // координаты линии
                        if(isset($figure["POINTS"][$i+1]["Y"])){
                            $x1 = $figure["POINTS"][$i]["X"] + $offsetX;
                            $y1 = $figure["POINTS"][$i]["Y"] + $offsetY;
                            $x2 = $figure["POINTS"][$i+1]["X"] + $offsetX;
                            $y2 = $figure["POINTS"][$i+1]["Y"] + $offsetY;

                        // замыкаем
                        }elseif($figure["CLOSED"]){
                            $x1 = $figure["POINTS"][$i]["X"] + $offsetX;
                            $y1 = $figure["POINTS"][$i]["Y"] + $offsetY;
                            $x2 = $figure["POINTS"][0]["X"] + $offsetX;
                            $y2 = $figure["POINTS"][0]["Y"] + $offsetY;
                        }
                        // сумма кватратов разности начальной и конечной точек
                        $sum =  pow($x2 -  $x1, 2 )+ pow($y2 - $y1,2);
                        // длина линии по координатам sqrt($sum)
                        $lengthContour += sqrt($sum);
                        // добавление линии
                        imageline ($img, $x1, $y1, $x2, $y2, $white);
                    }

                    break;

                case "CIRCLE":
                    imagearc(   $img,
                        $figure["POINTS"][0]["X"] + $offsetX,
                        $figure["POINTS"][0]["Y"] + $offsetY,
                        $figure["RADIUS"]*2,
                        $figure["RADIUS"]*2,
                        0, 360, $red);
                    // длина окружности
                    $lengthContour += 2*pi()*$figure["RADIUS"];
                    break;

                case "ARC":
                    imagearc(   $img,
                        $figure["POINTS"][0]["X"] + $offsetX,
                        $figure["POINTS"][0]["Y"] + $offsetY,
                        $figure["RADIUS"]*2, $figure["RADIUS"]*2,
                        $figure["START_ANGLE"], $figure["END_ANGLE"], $red);
                    // длина дуги
                    $angle = abs($figure["END_ANGLE"]-$figure["START_ANGLE"]);
                    $radians = pi()*$figure["RADIUS"]/180;
                    $lengthContour += $radians*$angle*pi();

                    break;
            }
        }

        echo "Длина контуров фигур : ".sprintf("%01.2f", $lengthContour)."<br>";

        echo "Количество фигур : ".count($figures)."<br><br>";

        ob_start ();
        // зеркальное отражение
        imageflip($img, IMG_FLIP_VERTICAL);
        // рендерим в поток картинку
        imagejpeg ($img);
        $image_data = ob_get_contents ();
        ob_end_clean ();
        imagedestroy($img);
        $image_data_base64 = base64_encode ($image_data);

        echo "<img width='100%' src='data:image/png;base64,$image_data_base64'>";

    }else{

       echo "<p>Некорректный формат файла</p>";

    }

}
?>
</body>
</html>