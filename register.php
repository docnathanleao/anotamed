<?php
// register.php

// Inicia a sessão apenas se não já iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já está logado, redireciona para o perfil/dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["username"])) {
        header("Location: /" . urlencode($_SESSION["username"])); // Para URL amigável do perfil
    } else {
        header("Location: dashboard.php"); // Fallback
    }
    exit;
}

// Pega mensagens de erro e sucesso da sessão
$register_err = "";
$register_success = "";

if (isset($_SESSION["register_error"])) {
    $register_err = htmlspecialchars($_SESSION["register_error"], ENT_QUOTES, 'UTF-8'); // Previne XSS
    unset($_SESSION["register_error"]);
}
if (isset($_SESSION["register_success"])) {
    $register_success = htmlspecialchars($_SESSION["register_success"], ENT_QUOTES, 'UTF-8'); // Previne XSS
    unset($_SESSION["register_success"]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - AnotaMed</title>
    <link rel="stylesheet" href="/css/style.css"> <!-- Caminho absoluto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        html {
            height: 100%;
            box-sizing: border-box;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100%; /* Ou min-height: 100vh; */
            background-color: #f0f2f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
        }

        .main-content-area {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px; /* Espaçamento para o container de registro */
            width: 100%;
        }

        .register-container {
            background: #fff;
            padding: 30px 40px; /* Consistente com login, se desejar */
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 380px;
            width: 100%; /* Ocupa 100% do max-width do pai, ou 100% do pai */
        }

        .register-container img.logo { /* Classe para a imagem */
            width: 70px;
            margin-bottom: 10px;
        }

        .register-container h2.app-title { /* Classe para o título */
            margin-top: 0;
            margin-bottom: 25px;
            color: #333; /* Consistência */
            font-size: 1.8em;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 15px; /* Ligeiramente menor para formulários de registro mais longos */
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] { /* Mais específico */
            width: 100%;
            padding: 10px; /* Consistente com login, se desejar, ou 12px */
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em; /* Removido box-sizing pois já está no '*' */
        }

        .btn-register {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s ease;
            font-weight: bold;
        }

        .btn-register:hover {
            background-color: #218838;
        }

        .message-display { /* Classe unificada para mensagens de erro e sucesso */
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: left;
        }
        .error-message { /* Herda de .message-display e adiciona cores específicas */
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .success-message { /* Herda de .message-display e adiciona cores específicas */
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .login-link {
            margin-top: 20px;
            font-size: 0.9em;
        }

        .login-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .footer-text {
            color: #bfbbbb;
            font-size: 0.9em;
            text-align: center;
            width: 100%;
            padding: 15px 0;
            flex-shrink: 0;
        }

        .writing-effect {
            color: #bfbbbb;
            animation: writingAnimation 4s ease-in-out infinite alternate;
        }

        @keyframes writingAnimation {
            from { opacity: 0.2; }
            to   { opacity: 0.8; }
        }
    </style>
</head>
<body>
    <div class="main-content-area">
        <div class="register-container">
            <img src="/images/anotamed1.png" alt="Logo AnotaMed" class="logo"> <!-- Caminho absoluto e classe -->
            <h2 class="app-title">anotamed</h2> <!-- Classe e removido estilo inline -->
            
            <?php if (!empty($register_err)): ?>
                <p class="message-display error-message"><?php echo $register_err; ?></p>
            <?php endif; ?>
            <?php if (!empty($register_success)): ?>
                <p class="message-display success-message"><?php echo $register_success; ?></p>
            <?php endif; ?>
            
            <form action="register_process.php" method="post" novalidate>
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" name="username" id="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-register">Registrar</button>
            </form>
            <p class="login-link">Já tem uma conta? <a href="login.php">Faça login</a></p>
        </div>
    </div>

    <p class="footer-text writing-effect">synamed</p>
</body>
</html>