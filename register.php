<?php
// register.php

// Inicia a sessão apenas se não já iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já está logado, redireciona para o perfil
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["username"])) {
        header("Location: /" . urlencode($_SESSION["username"]));
    } else {
        // Fallback - Limpar estado inválido
        error_log("[Register Page] User logged in but username missing from session. Redirecting to logout.");
        header("Location: logout.php"); 
    }
    exit;
}

// Pega mensagens de erro e sucesso da sessão (já tratadas em register_process.php)
$register_err = $_SESSION["register_error"] ?? \"\";
$register_success = $_SESSION["register_success"] ?? \"\";

// Limpa as mensagens da sessão após pegá-las
unset($_SESSION["register_error"], $_SESSION["register_success"]);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - AnotaMed</title>
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
            
            <?php if (!empty($register_err)): ?>
                <p class="message-display error-message"><?php echo $register_err; /* Vem do register_process, pode conter <br> */ ?></p>
            <?php endif; ?>
            <?php if (!empty($register_success)): ?>
                <p class="message-display success-message"><?php echo htmlspecialchars($register_success, ENT_QUOTES, \'UTF-8\'); ?></p>
            <?php endif; ?>
            
            <form action="register_process.php" method="post" novalidate>
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" name="username" id="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password" minlength="8"> <!-- Adicionado minlength -->
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password" minlength="8">
                </div>
                <button type="submit" class="btn-auth btn-register">Registrar</button> 
            </form>
            <p class="auth-link"> 
                Já tem uma conta? <a href="login.php">Faça login</a>
            </p>
        </div>
    </div>

    <footer class="auth-footer"> 
        <span class="writing-effect">synamed</span>
    </footer>

</body>
</html>
