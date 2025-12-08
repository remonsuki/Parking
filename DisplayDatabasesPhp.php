<?php
// MySQL connection
$mysqli = new mysqli('localhost', 'root', '123456', 'parking_db');
if ($mysqli->connect_error) {
    die('MySQL Connection Error: ' . $mysqli->connect_error);
}
$mysql_result_user = $mysqli->query('SELECT * FROM user');
$mysql_result_vehicle = $mysqli->query('SELECT * FROM vehicle');
$mysql_result_park_record = $mysqli->query('SELECT * FROM park_record');
$mysql_result_violation_record = $mysqli->query('SELECT * FROM violation_record');

// MongoDB connection
require 'vendor/autoload.php';
$mongoClient = new MongoDB\Client('mongodb://localhost:27017');
$mongoCollection = $mongoClient->parkingNoSqldb->parkingdb;
$mongo_result = $mongoCollection->find();
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Database Display</title>
</head>

<body>
    <h2>MySQL: user</h2>
    <table border="1" cellpadding="5">
        <tr>
            <?php
            if ($mysql_result_user && $mysql_result_user->num_rows > 0) {
                $columns = array_keys($mysql_result_user->fetch_assoc());
                foreach ($columns as $col)
                    echo "<th>$col</th>";
                $mysql_result_user->data_seek(0);
                foreach ($mysql_result_user as $row) {
                    echo '<tr>';
                    foreach ($columns as $col)
                        echo '<td>' . $row[$col] . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tr>
    </table>

    <h2>MySQL: Vehicle</h2>
    <table border="1" cellpadding="5">
        <tr>
            <?php
            if ($mysql_result_vehicle && $mysql_result_vehicle->num_rows > 0) {
                $columns = array_keys($mysql_result_vehicle->fetch_assoc());
                foreach ($columns as $col)
                    echo "<th>$col</th>";
                $mysql_result_vehicle->data_seek(0);
                foreach ($mysql_result_vehicle as $row) {
                    echo '<tr>';
                    foreach ($columns as $col)
                        echo '<td>' . $row[$col] . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tr>
    </table>

    <h2>MySQL: park_record</h2>
    <table border="1" cellpadding="5">
        <tr>
            <?php
            if ($mysql_result_park_record && $mysql_result_park_record->num_rows > 0) {
                $columns = array_keys($mysql_result_park_record->fetch_assoc());
                foreach ($columns as $col)
                    echo "<th>$col</th>";
                $mysql_result_park_record->data_seek(0);
                foreach ($mysql_result_park_record as $row) {
                    echo '<tr>';
                    foreach ($columns as $col)
                        echo '<td>' . $row[$col] . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tr>
    </table>

    <h2>MySQL: violation_record</h2>
    <table border="1" cellpadding="5">
        <tr>
            <?php
            if ($mysql_result_violation_record && $mysql_result_violation_record->num_rows > 0) {
                $columns = array_keys($mysql_result_violation_record->fetch_assoc());
                foreach ($columns as $col)
                    echo "<th>$col</th>";
                $mysql_result_violation_record->data_seek(0);
                foreach ($mysql_result_violation_record as $row) {
                    echo '<tr>';
                    foreach ($columns as $col)
                        echo '<td>' . $row[$col] . '</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tr>
    </table>

    <h2>MongoDB Collection</h2>
    <table border="1" cellpadding="5">
        <tr>
            <th>Document</th>
        </tr>
        <?php
        foreach ($mongo_result as $document) {
            echo '<tr><td><pre>' . json_encode($document, JSON_PRETTY_PRINT) . '</pre></td></tr>';
        }
        ?>
    </table>
</body>

</html>