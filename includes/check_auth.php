<?php
// Inicia a sessão se ainda não foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado, senão redireciona para a página de login
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>