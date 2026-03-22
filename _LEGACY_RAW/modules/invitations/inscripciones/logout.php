<?php
/**
 * Cerrar Sesión de Inscripciones
 */

session_start();
session_destroy();

header("Location: login.php");
exit;










