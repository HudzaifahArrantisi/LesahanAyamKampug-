<?php
session_start();
session_unset();
session_destroy();

// balik ke index
header("Location: index.php");
exit();
?>
