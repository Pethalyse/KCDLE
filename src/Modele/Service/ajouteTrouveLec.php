<?php
try{
    $bdd = new PDO("mysql:host=db5015045884.hosting-data.io;dbname=dbs12498527;charset=utf8;", "dbu4126087",'8mpRwAY!y2W$QAD');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch (PDOException $e)
{

}

$sql = "UPDATE `nbReussites` SET `nbPersonnes`= nbPersonnes+1 WHERE dle = 'lecdle' AND date = CURRENT_DATE ";

$pdo = $bdd->prepare($sql);
$pdo->execute();
