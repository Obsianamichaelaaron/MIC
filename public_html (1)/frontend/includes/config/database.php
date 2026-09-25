<?php
function getDBConnection() {
    $servername = "localhost";
    $username = "u190037990_multibiz";
    $password = "multibizPass1";
    $dbname = "u190037990_mb_db";

    mysqli_report(MYSQLI_REPORT_OFF);

    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn && $conn->connect_error) {
            return null;
        }
        return $conn;
    } catch (Exception $e) {
        return null;
    }
}
?>