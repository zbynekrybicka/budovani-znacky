<?php
require_once 'vendor/autoload.php';
require_once 'common.php';

function getMyslenky($db, $myslenkaId) {
    $myslenky = $db->select('*')->from('podklay_myslenky')->where('myslenka_id = %u', $myslenkaId)->fetchAll();
    foreach ($myslenky as $myslenka) {
        $myslenky = array_merge($myslenky, getMyslenky($db, $myslenka->id));
    }
    return $myslenky;
}

$userId = checkAuth();
if (!$userId) {
    http_response_code(401);
} else {

    $subordinates = getSubordinates($db, $userId);
    $superiors = getSuperiors($db, $userId);

    $podkladyTemata = $db->select('*')->from('podklady_temata')->where('osoba_id IN %in', $subordinates)->fetchAll();
    
    $podkladyMyslenky = [];
    foreach ($podkladyTemata as $tema) {
        $podkladyMyslenky = array_merge($myslenky, getMyslenky($db, $tema->myslenka_id));
    }

    $podkladyVety = [];
    foreach ($podkladyMyslenky as $myslenka) {
        $vety = $db->select('*')->from('podklady_vety')->where('myslenka_id = %u', $myslenka->id)->fetchAll();
        $podkladyVety = array_merge($podkladyVety, $vety);
    }

    echo json_encode([ 
        'userId' => $userId,
        'subordinates' => $subordinates,
        'superiors' => $superiors,
        'podklady_temata' => $podkladyTemata,
        'podklady_myslenky' => $podkladyMyslenky,
    ]);
}