<?php
session_start(); // Necessário para verificar se o usuário está logado

// Se o usuário já está logado e acessa a raiz, redireciona para o dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// Caso contrário, redireciona o navegador para a página de login padrão
header('Location: login.php'); // Certifique-se que o nome está correto
exit(); // Importante parar a execução após o redirecionamento
?>