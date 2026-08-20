<?php

$password = "We3x@0908";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo $hashedPassword;