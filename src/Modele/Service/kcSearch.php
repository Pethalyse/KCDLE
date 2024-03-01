<?php

$like = $_GET["like"];

try{
   	$bdd = new PDO("mysql:host=db5015045884.hosting-data.io;dbname=dbs12498527;charset=utf8;", "dbu4126087",'8mpRwAY!y2W$QAD');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch (PDOException $e)
{

}

$sql = "SELECT * from JoueursKCDLE WHERE Pseudo LIKE :l ORDER BY Pseudo; ";

$pdo = $bdd->prepare($sql);
$pdo->execute(array("l" => $like ."%"));
$res = $pdo->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($res);