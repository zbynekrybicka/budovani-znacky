<?php
require_once 'vendor/autoload.php';
require_once 'common.php';


function validate($db, $data, $userId) {
    $result = [];
    if (!strlen($data->nazev)) {
        $result['nazev'] = "Název nesmí být prázdný";
    }
    if (!$data->myslenka_id) {
        $result['myslenka_id'] = "myslenka_id nesmí být prázdné";
    }
    if (!validateAutor($db, $data->myslenka_id, $userId)) {
        $result['myslenka_id'] = "Myšlenka se nevztahuje k tématu patřícímu autorovi.";
    }
    return $result;
}

function validateAutor($db, $myslenka_id, $userId) {
    do {
        $myslenka = $db->select('id, myslenka_id')->from('podklady_myslenky')->where('id = %u', $myslenka_id)->fetch();
    } while ($myslenka->myslenka_id);
    return patriMyslenkaAutorovi($db, $myslenka, $userId);
}


function autorizujMyslenku($db, $myslenkaId, $userId) {
    $myslenka = $db->select('*')->from('podklady_myslenky')->where('id = %u', $myslenkaId)->fetch();
    if (!$myslenka) {
        return "Zvolená myšlenka neexistuje.";
    }

    if ($myslenka->myslenka_id) {
        $validniAutor = validateAutor($db, $myslenka->myslenka_id, $userId);
    } else {
        $validniAutor = patriMyslenkaAutorovi($db, $myslenka, $userId);
    }
    if (!$validniAutor) {
        return "Myšlenka nepatří autorovi.";
    }
    return false;
}


function patriMyslenkaAutorovi($db, $myslenka, $userId) {
    $tema = $db->select('count(*)')->from('podklady_temata')->where('myslenka_id = %u AND osoba_id = %u', $myslenka->id, $userId)->fetchSingle();
    return $tema === 1;
}

$userId = checkAuth();
if (!$userId) {
    http_response_code(401);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $chyby = validate($db, $data, $userId);
    if (count($chyby)) {
        http_response_code(400);
        echo json_encode($chyby);
        exit;
    }

    $poradi = $db->select('count(*)')->from('podklady_myslenky')->where('myslenka_id = %u', $data->myslenka_id)->fetchSingle();
    $podkladyMyslenka = [
        'myslenka_id' => $data->myslenka_id,
        'nazev' => $data->nazev,
        'poradi' => $poradi
    ];
    $db->insert('podklady_myslenky', $podkladyMyslenka)->execute();
    $podkladyMyslenka['id'] = $db->getInsertId();

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode($podkladyMyslenka);

} else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    if (!isset($data->nazev) || !strlen($data->nazev)) {
        http_response_code(400);
        echo json_encode("Není vyplněný název myšlenky nebo je prázdný.");
        exit;
    }

    $chyba = autorizujMyslenku($db, $data->id, $userId);
    if ($chyba) {
        http_response_code(400);
        echo json_encode($chyba);
        exit;
    }

    $db->update('podklady_myslenky', ['nazev' => $data->nazev])->where('id = %u', $data->id)->execute();
    http_response_code(204);

} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $myslenka = $db->select('*')->from('podklady_myslenky')->where('id = %u', $_GET['id'])->fetch();

    if (!$myslenka) {
        http_response_code(400);
        echo json_encode("Zvolená myšlenka neexistuje.");
        exit;
    }

    if ($myslenka->myslenka_id === null) {
        http_response_code(400);
        echo json_encode("Nelze smazat kořenovou myšlenku. Smažte téma, ke kterému se vztahuje.");
        exit;
    }


    $chyba = autorizujMyslenku($db, $_GET['id'], $userId);
    if ($chyba) {
        http_response_code(400);
        echo json_encode($chyba);
        exit;
    }

    $db->delete('podklady_myslenky')->where('id = %u', $myslenka->id)->execute();
    http_response_code(204);

} else {
    http_response_code(405);
}