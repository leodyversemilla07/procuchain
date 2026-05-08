<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->exec("CREATE USER IF NOT EXISTS 'procuchain'@'%' IDENTIFIED BY ''");
$pdo->exec("GRANT ALL PRIVILEGES ON procuchain.* TO 'procuchain'@'%'");
$pdo->exec("FLUSH PRIVILEGES");
echo "Done. "; 
$test = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procuchain', 'procuchain', '');
echo "Connected as procuchain!";