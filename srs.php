<?php

require_once("SocketServer.class.php"); // Include the File
//require_once("alerts.php"); // Include the File
//$server = new SocketServer("10.164.253.57",8090); // Create a Server binding to the given ip address and listen to port 31337 for connections
//$server = new SocketServer("10.0.0.1",8106); // Create a Server binding to the given ip address and listen to port 31337 for connections
$server = new SocketServer("184.73.175.95" , 5432);
$server->max_clients = 1000; // Allow no more than 10 people to connect at a time
$server->hook("CONNECT","handle_connect"); // Run handle_connect every time someone connects
$server->hook("INPUT","handle_input"); // Run handle_input whenever text is sent to the server
$server->infinite_loop(); // Run Server Code Until Process is terminated.

function handle_connect(&$server,&$client,$input)
{
    SocketServer::socket_write_smart($client->socket,"String? ","");
}

function pointStringToCoordinates($pointString)
{
  $coordinates = explode(",", $pointString);
  return array("x" => trim($coordinates[0]), "y" => trim($coordinates[1]));
}
function isWithinBoundary($point,$polygon)
{
  $result =FALSE;
  $point = pointStringToCoordinates($point);
  $vertices = array();
  foreach ($polygon as $vertex)
  {
    $vertices[] = pointStringToCoordinates($vertex);
  }
  // Check if the point is inside the polygon or on the boundary
  $intersections = 0; 
  $vertices_count = count($vertices);
  for ($i=1; $i < $vertices_count; $i++)
  {
    $vertex1 = $vertices[$i-1]; 
    $vertex2 = $vertices[$i];
    if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x']))
    {
      // This point is on an horizontal polygon boundary
      $result = TRUE;
      // set $i = $vertices_count so that loop exits as we have a boundary point
      $i = $vertices_count;
    }
    if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y'])
    {
      $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x']; 
      if ($xinters == $point['x'])
      {
        // This point is on the polygon boundary (other than horizontal)
          $result = TRUE;
        // set $i = $vertices_count so that loop exits as we have a boundary point
        $i = $vertices_count;
      }
      if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters)
      {
        $intersections++;
      }
      } 
  }
  // If the number of edges we passed through is even, then it's in the polygon. 
  // Have to check here also to make sure that we haven't already determined that a point is on a boundary line
  if ($intersections % 2 != 0 && $result == FALSE)
  {
    $result = TRUE;
  }
  return $result;
}


function ToDegrees($val){
                $GPRMC2Degrees = intval($val / 100) + ($val - (intval($val / 100) * 100)) / 60;
                return $GPRMC2Degrees;
}

function handle_input($server,&$client,$input)
{
  $servername = "bigperl.cqhgvggnarsv.us-east-2.rds.amazonaws.com";
$username = "root";
$password = "";
$dbname = "bigperldb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
 // mysqli_connect('localhost','root','anish_db');
 //  mysql_select_db('ossgps_shop');
    // You probably want to sanitize your inputs here
    $trim =  trim($input); // Trim the input, Remove Line Endings and Extra Whitespace.
    $trimo =$trim; // Trim the input, Remove Line Endings and Extra Whitespace.
  $trim= str_replace('oil','',$trim);
  $trim= str_replace('%','',$trim);
  $trim= str_replace(' imei:', '',$trim);
  $trim=explode(",", $trim);
  
  

  $date_time = $trim[6];        //date-time
  $gps_valid = $trim[7];  
  $lat = $trim[4];    // lat
  $lng = $trim[5];    // lang
  $speed = $trim[10];           //speed
 $imei=$trim[1]; 
 
 $qwn=mysqli_query($conn,"SELECT * FROM `newmetrack` where imei='$imei' and engine_status!=''  ORDER BY `id`  DESC limit 1");
if($qwn){
  $rrrn=mysqli_fetch_array($qwn);
  $acc=$rrrn['engine_status'];
  if($acc=='ACCStop'){$acc='acc off';}else{$acc='acc on';}
}

  $engine_status = $trim[3];      //engine_status 
  if($engine_status==2){
    $engine_status='ACCStart';
  $acc='acc on';
  }
  if($engine_status==10){
    $engine_status='ACCStop';
  $acc='acc off';
  }
  /*
  if($engine_status==10){
    $engine_status='Trigger3Start';
  }
  if($engine_status==2){
    $engine_status='Trigger3Stop';
  }
  */
  if($engine_status==35){
      $engine_status='';
  }
  if($engine_status==23){
    $engine_status='powercut';
    // SEND ALERT
    $qq=mysqli_query($conn,"SELECT customer_id,device_name FROM `installation` where imie_no='$imei' limit 1");
    if($qq){
      $r=mysql_fetch_array($qq);
      $device_name=$r['device_name'];
      $cid= $r['customer_id'];

      $qq=mysqli_query($conn,"SELECT customer_phone_no FROM `customers` where customer_id='$cid' LIMIT 1");
    if($qq && mysql_num_rows($qq)>0)
    {
      $rr= mysqli_fetch_array($qq);
      $phone=$rr['customer_phone_no'];
    }

  }
    else{
      $device_name=$trim[1];
    }
    $ch = curl_init();
        $msg="OGTS Alert! Power supply has been disconnected for  $device_name.";
        $msg=urlencode($msg);
        $url ="http://alerts.sinfini.com/api/web2sms.php?workingkey=121927e188m35t3n5q96a&sender=ossgps&to=$phone&message=$msg";
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $a=curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);
  }
   if($engine_status==28){
    $engine_status='antenacut';
    // SEND ALERT
     $qq=mysqli_query($conn,"SELECT customer_id,device_name FROM `installation` where imie_no='$imei' limit 1");
    if($qq){
      $r=mysqli_fetch_array($qq);
      $device_name=$r['device_name'];
      $cid= $r['customer_id'];

      $qq=mysqli_query($conn,"SELECT customer_phone_no FROM `customers` where customer_id='$cid' LIMIT 1");
    if($qq && mysql_num_rows($qq)>0)
    {
      $rr= mysqli_fetch_array($qq);
      $phone=$rr['customer_phone_no'];
    }

    }
    else{
      $device_name=$trim[1];
    }
    $ch = curl_init();
        $msg="OGTS Alert!GPS antenna has been cut for  $device_name.";
        $msg=urlencode($msg);
        $url ="http://alerts.sinfini.com/api/web2sms.php?workingkey=121927e188m35t3n5q96a&sender=ossgps&to=$phone&message=$msg";
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $a=curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);
  }


 if($engine_status==54){
    $engine_status='fuel_theft';
    // SEND ALERT
     $qq=mysqli_query($conn,"SELECT customer_id,device_name FROM `installation` where imie_no='$imei' limit 1");
    if($qq){
      $r=mysqli_fetch_array($qq);
      $device_name=$r['device_name'];
      $cid= $r['customer_id'];

      $qq=mysqli_query("SELECT customer_phone_no FROM `customers` where customer_id='$cid' LIMIT 1");
    if($qq && mysqli_num_rows($qq)>0)
    {
      $rr= mysqli_fetch_array($qq);
      $phone=$rr['customer_phone_no'];
    }

    }
    else{
      $device_name=$trim[1];
    }
    $ch = curl_init();
        $msg="OGTS Alert!Fuel theft in progress from $device_name.";
        $msg=urlencode($msg);
        $url ="http://alerts.sinfini.com/api/web2sms.php?workingkey=121927e188m35t3n5q96a&sender=ossgps&to=$phone&message=$msg";
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $a=curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);
  }
              //imei
  $oil=$trim[22];         //oil
  $ol = str_split($oil, 2);
  $aa=hexdec($ol[0]);
  $bb=hexdec($ol[1]);
  $oil=$aa.".".$bb;

// covert devicetime
  $arr = str_split($date_time, 2);
  $dt="20".$arr[0]."-".$arr[1]."-".$arr[2]." ".$arr[3].":".$arr[4].":".$arr[5];

  // from here you need to do your database stuff
  $query="INSERT INTO `newmetrack` (`id`, `imei`, `lat`, `lng`, `speed`, `engine_status`, `oil`, `gps_valid`, `device_time`, `time_stamp`) VALUES (NULL, '$imei', '$lat', '$lng', '$speed', '$engine_status', '$oil', '$gps_valid', '$dt', CURRENT_TIMESTAMP)";
  $result=mysqli_query($conn,$query);
  if($result){
    echo "\n 1 record inserted into newmetrack table \n";
  }
  else{
    echo "\n ERROR".mysqli_error();
  }
    get_fence($imei,$lat,$lng);
  speed_alert($imei,$speed,$lat,$lng);
  //idleAlert($imei,$lat,$lng);
  newIdleAlert($imei, $lat, $lng, $speed, $acc);
  tripAlerts($imei,$lat,$lng);


    $output = $trim; // Reverse the String

   SocketServer::socket_write_smart($client->socket,"waiting..."); // Send the Client back the String

 //   SocketServer::socket_write_smart($client->socket,"String? ",""); // Request Another String
mysqli_close();
}

