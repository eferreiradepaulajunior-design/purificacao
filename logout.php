<?php
// ARQUIVO: logout.php
// Destrói a sessão e redireciona para a página de login.

session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;