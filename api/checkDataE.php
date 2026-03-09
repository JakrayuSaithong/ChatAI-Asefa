<?php
include("../../api/vendor/auth.php");

$DataE = $_POST['DataE'] ?? $_GET['DataE'] ?? '';

if (!empty($DataE)) {
    $JsonText = decryptIt($DataE);
    $JSOnArr = json_decode($JsonText, true);
} else {
    $JSOnArr = null;
}

if (!empty($JSOnArr) && !empty($JSOnArr['auth_user_name'])) {
    $Users_Username = $JSOnArr['auth_user_name'];

    $get_emp_detail = "https://innovation.asefa.co.th/applications/ds/emp_list_code";
    $chs = curl_init();
    curl_setopt($chs, CURLOPT_URL, $get_emp_detail);
    curl_setopt($chs, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chs, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($chs, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($chs, CURLOPT_POST, 1);
    curl_setopt($chs, CURLOPT_POSTFIELDS, ["emp_code" => $Users_Username]);
    $emp = curl_exec($chs);
    curl_close($chs);

    $empdata = json_decode($emp);

    if (!empty($empdata) && !empty($empdata[0]->emp_code)) {
        $Users_Username = $empdata[0]->emp_code;
        $Users_Image = $empdata[0]->emp_Image ?? '';

        $dataArr = [
            "ASEFA" => true,
            "DATA" => [
                "Users_Username" => $Users_Username,
                "Users_Image" => $Users_Image
            ]
        ];

        echo json_encode($dataArr, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["ASEFA" => false], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(["ASEFA" => false], JSON_UNESCAPED_UNICODE);
}
