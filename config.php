<?php

	$servername = "sunrisesql.playsamp.fun";
    $username = "u3_YIcbXbJcGr";
    $password = "p!bt3YORm=iQVlCU5+WM0Ycd";
    $dbname = "s3_sunriserp";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

?>
