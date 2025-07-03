<?php

	$servername = "neutron.optiklink.com";
    $username = "u247379_Wyc8AuJUck";
    $password = "@Ev1I0Em3QjzO8GEJ!wtFGnw";
    $dbname = "s247379_sunriserp";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

?>
