<?php
$url='http://localhost/testsignup/api/login.php';
$data=json_encode(['phone'=>'1000000002','password'=>'password']);
$opts=['http'=>['method'=>'POST','header'=>'Content-Type: application/json\r\n','content'=>$data,'ignore_errors'=>true]];
$context=stream_context_create($opts);
$res=file_get_contents($url,false,$context);
echo $res;
