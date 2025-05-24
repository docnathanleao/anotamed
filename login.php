<?php
// login.php

// Inicia a sessão apenas se não já iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já está logado, redireciona para o perfil
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["username"])) {
        header("Location: /" . urlencode($_SESSION["username"]));
    } else {
        // Fallback se username não estiver na sessão (improvável, mas seguro)
        // Redirecionar para logout pode ser uma opção para limpar estado inválido
        error_log("[Login Page] User logged in but username missing from session. Redirecting to logout.");
        header("Location: logout.php"); 
    }
    exit;
}

// Pega a mensagem de erro da sessão, se houver
$login_err = "";
if (isset($_SESSION["login_error"])) {
    // A mensagem já deve vir tratada do auth.php, mas escapar aqui é uma defesa extra.
    $login_err = htmlspecialchars($_SESSION["login_error"], ENT_QUOTES, \'UTF-8\
    ');
    unset($_SESSION["login_error"]); // Limpa o erro após pegar
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AnotaMed</title>
    <!-- CSS Principal -->
    <link rel="stylesheet" href="/css/style.css"> 
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/images/icon.png">
    <!-- REMOVIDO: Bloco <style> interno -->
</head>
<body class="auth-page"> 

    <div class="auth-main-content-area">
        <div class="auth-container"> 
            <img src="/images/anotamed1.png" alt="Logo AnotaMed" class="logo">
            <h2 class="app-title">anotamed</h2>
            
            <?php if (!empty($login_err)): ?>
                <p class="message-display error-message"><?php echo $login_err; ?></p>
            <?php endif; ?>
            
            <form action="auth.php" method="post" novalidate>
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" name="username" id="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-auth btn-login">Entrar</button> 
            </form>
            <p class="auth-link"> 
                Não tem uma conta? <a href="register.php">Registre-se</a>
            </p>
        </div>
    </div>

    <footer class="auth-footer"> 
        <span class="writing-effect">synamed</span>
    </footer>

</body>
</html>
