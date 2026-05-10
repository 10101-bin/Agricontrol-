<?php
session_start();
session_destroy();
header('Location: /agricontrol/pagina_principal.html');
exit();
?>