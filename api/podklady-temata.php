<?php
require_once 'vendor/autoload.php';
require_once 'common.php';

$userId = checkAuth();

function validate($data) {
    $result = [];
    if (!strlen($data->nazev)) {
        $result['nazev'] = "Název nesmí být prázdný";
    }
    return $result;
}

if (!$userId) {
    http_response_code(401);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $chyby = validate($data);
    if (count($chyby)) {
        http_response_code(400);
        echo json_encode($chyby);
        exit;
    }

    $podkladyMyslenka = [
        'myslenka_id' => null,
        'nazev' => $data->nazev,
        'poradi' => 0
    ];
    $db->insert('podklady_myslenky', $podkladyMyslenka)->execute();
    $podkladyMyslenka['id'] = $db->getInsertId();

    $poradi = $db->select('count(id)')->from('podklady_temata')->where('osoba_id = %u', $userId)->fetchSingle();
    $podkladyTema = [
        'osoba_id' => $userId,
        'myslenka_id' => $podkladyMyslenka['id'],
        'poradi' => $poradi,
        'created_at' => time()
    ];
    $db->insert('podklady_temata', $podkladyTema)->execute();
    $podkladyTema['id'] = $db->getInsertId();

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode([
        'podklady_myslenky' => $podkladyMyslenka,
        'podklady_temata' => $podkladyTema
    ]);

} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $podkladyTema = $db->select('*')->from('podklady_temata')->where('id = %u AND osoba_id = %u', $_GET['id'], $userId)->fetch();
    if (!$podkladyTema) {
        http_response_code(400);
        echo json_encode("Téma neexistuje nebo nepatří danému uživateli.");
        exit;
    }
    $db->delete('podklady_myslenky')->where('id = %u', $podkladyTema->myslenka_id)->execute();
    $db->delete('podklady_temata')->where('id = %u AND osoba_id = %u', $_GET['id'], $userId)->execute();
    http_response_code(204);

} else {
    http_response_code(405);
}