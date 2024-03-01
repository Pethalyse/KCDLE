<?php

$time = $_GET["time"];

try{
    $bdd = new PDO("mysql:host=localhost;dbname=lfldle;charset=utf8;", "root",'');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch (PDOException $e)
{

}

$sql = "SELECT * from updates";

$pdo = $bdd->prepare($sql);
$pdo->execute();
$res = $pdo->fetch();

if($res[0] != $time){
    $sql = "UPDATE updates SET time = :time; call UpdateLEC;";

    $pdo = $bdd->prepare($sql);
    $pdo->execute(array("time" => $time));
}

echo json_encode($res[0]);