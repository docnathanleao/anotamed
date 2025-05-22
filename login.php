<?php
// login.php

// É uma boa prática definir constantes para caminhos ou URLs base se forem usados em vários lugares.
// Se você já estiver usando a Abordagem 1 com URLs amigáveis como /username,
// o redirecionamento do dashboard deve ir para lá.
// define('DASHBOARD_URL', 'dashboard.php'); // Ou '/NOMEDOUSUARIO_LOGADO' se já implementado

// Inicia a sessão
if (session_status() == PHP_SESSION_NONE) { // Inicia a sessão apenas se não já iniciada
    session_start();
}

// Se o usuário já está logado, redireciona para o dashboard (ou perfil)
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["username"])) {
        // Se estiver usando a abordagem de URLs amigáveis para o perfil:
        header("Location: /" . urlencode($_SESSION["username"]));
    } else {
        // Fallback para um dashboard.php genérico se o username não estiver na sessão por algum motivo
        header("Location: dashboard.php"); // Ou uma página de erro/logout forçado
    }
    exit;
}

// Pega a mensagem de erro da sessão, se houver
$login_err = "";
if (isset($_SESSION["login_error"])) {
    $login_err = htmlspecialchars($_SESSION["login_error"], ENT_QUOTES, 'UTF-8'); // Previne XSS ao exibir o erro
    unset($_SESSION["login_error"]); // Limpa o erro após pegar
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AnotaMed</title>
    <!-- Usar caminhos absolutos para CSS é mais robusto com reescrita de URL -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Recomenda-se mover a maior parte desses estilos para style.css para melhor organização,
           mas para uma página única como login, mantê-los aqui é aceitável. */
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
            padding: 20px;
            width: 100%;
        }

        .login-container {
            background: #fff;
            padding: 30px 40px; /* Ajustado para consistência com register, se desejar */
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 380px;
            width: 100%; /* Ocupa 100% do max-width do pai se for menor, senão 100% do pai */
        }

        .login-container img.logo { /* Classe para a imagem para melhor especificidade */
            width: 70px;
            margin-bottom: 10px;
        }

        .login-container h2.app-title { /* Classe para o título para melhor especificidade */
            margin-top: 0; /* Remover margem do topo se a imagem já der espaço */
            margin-bottom: 25px;
            color: #333; /* Alterado do inline #000000 para consistência */
            font-size: 1.8em;
            font-weight: 700; /* Exemplo de peso da fonte */
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold; /* Ou 700 */
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] { /* Mais específico */
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em; /* Removido box-sizing pois já está no seletor global '*' */
        }

        .btn-login {
            background-color: #007bff;
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

        .btn-login:hover {
            background-color: #0056b3;
        }

        .error-message {
            color: #721c24; /* Cor do texto do erro */
            background-color: #f8d7da; /* Fundo da caixa de erro */
            border: 1px solid #f5c6cb; /* Borda da caixa de erro */
            padding: 10px 15px; /* Ajuste no padding */
            border-radius: 4px;
            margin-bottom: 20px; /* Aumentado um pouco */
            font-size: 0.9em;
            text-align: left;
        }

        .register-link {
            margin-top: 20px;
            font-size: 0.9em;
        }

        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .footer-text {
            color: #bfbbbb; /* Cor base do seu texto quando visível */
            font-size: 0.9em;
            text-align: center;
            width: 100%;
            padding: 15px 0; /* Aumentado padding vertical */
            flex-shrink: 0; /* Impede que o footer encolha */
            /* animation: writingAnimation 4s ease-in-out infinite alternate; */ /* Movido para .writing-effect */
        }

        .writing-effect {
            /* Herda font-family do body */
            color: #bfbbbb; /* Cor base do texto quando visível */
            animation: writingAnimation 4s ease-in-out infinite alternate;
        }

        @keyframes writingAnimation {
            from { opacity: 0.2; } /* Começa um pouco mais visível */
            to   { opacity: 0.8; } /* Não chega a ser totalmente opaco para um efeito mais sutil */
        }
    </style>
</head>
<body>
    <div class="main-content-area">
        <div class="login-container">
            <img src="/images/anotamed1.png" alt="Logo AnotaMed" class="logo"> <!-- Adicionada classe logo -->
            <h2 class="app-title">anotamed</h2> <!-- Adicionada classe app-title e removido estilo inline -->
            
            <?php if (!empty($login_err)): ?>
                <p class="error-message"><?php echo $login_err; // Já foi "escapado" com htmlspecialchars ?></p>
            <?php endif; ?>
            
            <form action="auth.php" method="post" novalidate> <!-- novalidate para desabilitar validação HTML5 se quiser confiar só no PHP -->
                <div class="form-group">
                    <label for="username">Usuário:</label>
                    <input type="text" name="username" id="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">Entrar</button>
            </form>
            <p class="register-link">Não tem uma conta? <a href="register.php">Registre-se</a></p>
        </div>
    </div>

    <p class="footer-text writing-effect">synamed</p>
</body>
</html>