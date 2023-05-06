<?php
include("config.php");
//listener ips holen
$outputNetstat = shell_exec('netstat -an|grep ESTABLISHED|grep -P "8080|8081"');

if ($outputNetstat == null) {
    return;
}


$listener = getListener($outputNetstat);


//mongodb ips holen
require_once __DIR__ . '/vendor/autoload.php';

$client = new MongoDB\Client(
    'mongodb+srv://marv:' . PASSWORD . '@cluster0.4ejve.mongodb.net/myFirstDatabase?retryWrites=true&w=majority'
);
$db = $client->shelluser;

$collection = $client->shelluser->users;

$mongodb = getMongodb($collection);



//listener ips hinzufügen falls noch nicht in mongodb vorhanden
hinzufuegen($listener, $mongodb, $collection);
//wieder neue mongodb liste holen
$mongodb = getMongodb($collection);



//benachrichtigung an alle mongo ips die in listener ips sind wo notification auf true ist und danach auf false setzen
for ($i = 0; $i < count($mongodb); $i++) {

    $istDrin = false;
    $line = $mongodb[$i];
    $ip = $line[0];
    $notification = $line[1];

    for ($j = 0; $j < count($listener); $j++) {
        if ($ip == $listener[$j]) {
            $istDrin = true;
            break;
        }
    }

    if ($istDrin == true && $notification == true) {
        sendNotification($ip);
        setNotificationFalse($ip, $collection);
    }
}






















function sendNotification($ip)
{

    $message = "Bingo!+$ip+ist+mit+uns+verbunden";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.callmebot.com/whatsapp.php?phone=xxx&text=' . $message . '&apikey=xxx',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    //echo $response;
}




function setNotificationFalse($ip, $collection)
{
    $collection->updateOne(
        ['ip' => $ip],
        ['$set' => ['notification' => false]]
    );
}



function hinzufuegen($listener, $mongodb, $collection)
{
    $counter = 0;
    $bereitshinzugefuegt = array();
    for ($i = 0; $i < count($listener); $i++) {
        $ip = $listener[$i];

        $isIn = false;
        for ($j = 0; $j < count($mongodb); $j++) {
            $line = $mongodb[$j];
            if ($line[0] == $ip) {
                $isIn = true;
                break;
            }
        }

        if ($isIn == false) {
            $bereits = false;
            foreach ($bereitshinzugefuegt as $data) {
                if ($data == $ip) {
                    $bereits = true;
                }
            }
            if ($bereits == false) {
                $collection->insertOne([
                    'ip' => $ip,
                    'notification' => true,
                ]);
                $bereitshinzugefuegt[$counter] = $ip;
                $counter++;
            }
        }
    }
}

function getListener($outputNetstat)
{
    $output = array();
    $output = explode("ESTABLISHED", $outputNetstat);
    $output = preg_replace('!\s+!', ' ', $output);
    $output = str_replace(' ', '!', $output);

    $i = 0;
    $listener = array();
    foreach ($output as $ip) {

        if ($i == count($output) - 1) {
            break;
        }

        $line = explode("!", $ip);
        if ($i == 0) {
            $ip = $line[4];
        } else {
            $ip = $line[5];
        }
        $ip = explode(":", $ip);
        $listener[$i] = $ip[0];
        $i++;
    }
    return $listener;
}

function getMongodb($collection)
{
    $cursor = $collection->find();

    $mongodb = array();
    $i = 0;
    foreach ($cursor as $document) {
        $mongodb[$i][0] = $document["ip"];
        $mongodb[$i][1] = $document["notification"];
        $i++;
    }

    return $mongodb;
}
