<?php
require_once 'vendor/autoload.php';
require_once 'common.php';


function validate($db, $data, $userId) {
    $result = [];
    if (!$data->myslenka_id) {
        $result['myslenka_id'] = "myslenka_id nesmí být prázdné";
    }
    $myslenkaNepatriAutorovi = autorizujMyslenku($db, $data->myslenka_id, $userId);
    if ($myslenkaNepatriAutorovi) {
        $result['myslenka_id'] = $myslenkaNepatriAutorovi;
    }
    return $result;
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


function validateAutor($db, $myslenka_id, $userId) {
    do {
        $myslenka = $db->select('id, myslenka_id')->from('podklady_myslenky')->where('id = %u', $myslenka_id)->fetch();
    } while ($myslenka->myslenka_id);
    return patriMyslenkaAutorovi($db, $myslenka, $userId);
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

    $db->delete('podklady_vety')->where('myslenka_id = %u', $data->myslenka_id)->execute();

    $result = [];
    if (strlen($data->obsah)) {
        $obsah = explode("\n", str_replace("\r\n", "\n", $data->obsah));        
        foreach ($obsah as $veta) {
            $values = [
                'myslenka_id' => $data->myslenka_id,
                'obsah' => $veta
            ];
            $db->insert('podklady_vety', $values)->execute();
            $values['id'] = $db->getInsertId();
            $result[] = $values;
        }
    }   

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    http_response_code(405);
}