<?php

$email = "admin@gmail.com";
$password = "joshangi6345";

$hashed = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Admin Credentials</h3>";
echo "Email: " . $email . "<br><br>";
echo "Password Hash:<br>";
echo $hashed;

?>
