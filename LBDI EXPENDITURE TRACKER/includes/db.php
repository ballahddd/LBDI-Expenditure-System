<?php
declare(strict_types=1);

// InfinityFree MySQL settings.
// If your DB name is different, update DB_NAME only.
const DB_HOST = 'sql300.infinityfree.com';
const DB_PORT = 3306;
const DB_NAME = 'if0_41449894_finedge';
const DB_USER = 'if0_41449894';
const DB_PASS = 'Qo86MxjDzKFpNRS';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db_connect(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
    return $conn;
}

