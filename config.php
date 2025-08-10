<?php

	$servername = "neutron.optiklink.com";
    $username = "u247379_Wyc8AuJUck";
    $password = "++jOeOz908+aN1yC8DpkF@Mx";
    $dbname = "s247379_sunriserp";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

?>
