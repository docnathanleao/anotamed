<?php
// /password_entry.php
session_start();
require_once "includes/db_connect.php"; // Garante que $mysqli está disponível

$username_from_url = "";
$user_exists_in_db = false;
$show_dashboard_content = false;
$page_title = "AnotaMed"; // Título padrão

$profile_login_err = ""; // Para erros vindos do auth.php ao tentar logar desta página
if (isset($_SESSION["profile_login_error"])) {
    $profile_login_err = $_SESSION["profile_login_error"];
    unset($_SESSION["profile_login_error"]);
}

if (isset($_GET['username'])) {
    $username_from_url = trim($_GET['username']);

    if (empty($username_from_url)) {
        header("location: login.php"); // Redireciona se username da URL estiver vazio
        exit;
    }

    $sql_check = "SELECT id, username FROM users WHERE username = ?";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $stmt_check->bind_param("s", $param_username_check);
        $param_username_check = $username_from_url;
        if ($stmt_check->execute()) {
            $stmt_check->store_result();
            if ($stmt_check->num_rows == 1) {
                $user_exists_in_db = true;
                $page_title = htmlspecialchars($username_from_url) . " - AnotaMed";
            }
        }
        $stmt_check->close();
    }
}

if (!$user_exists_in_db) {
    $_SESSION["login_error"] = "Usuário \"" . htmlspecialchars($username_from_url) . "\" não encontrado ou URL inválida.";
    header("location: login.php");
    exit;
}

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["username"] === $username_from_url) {
        $show_dashboard_content = true;
    }
}

$body_background_color = $show_dashboard_content ? '#e9ecef' /* Cor de fundo do dashboard (ex: do body do style.css) */ : '#f0f2f5' /* Cor de fundo da tela de login */;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Principal (seu style.css já parece ser o do dashboard) -->
    <link rel="stylesheet" href="/css/style.css"> 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="/images/icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">

    <?php if ($show_dashboard_content): ?>
        <!-- Dependências CSS específicas do Dashboard (Font Awesome) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="icon" type="image/png" href="/images/icon.png">
    <?php endif; ?>

    <style>
        html {
            box-sizing: border-box;
            height: 100%; /* Garante que a altura total seja considerada */
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            background-color: <?php echo $body_background_color; ?>;
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; /* Ocupa toda a altura da viewport */
            
            <?php if (!$show_dashboard_content): /* Estilos para centralizar o formulário de login */ ?>
            justify-content: center; 
            align-items: center;   
            padding: 20px; 
            <?php else: /* Dashboard é mostrado, sem padding extra no body */ ?>
            padding: 0; 
            /* overflow-y: auto; /* Geralmente, o body é o scroll principal. Deixe o padrão ou defina se necessário. */
            <?php endif; ?>
        }

        .container-wrapper {
            width: 100%;
            margin: <?php echo $show_dashboard_content ? '0' : '0 auto'; ?>; 
            max-width: <?php echo $show_dashboard_content ? 'none' : '380px'; ?>; 
            
            <?php if ($show_dashboard_content): ?>
            flex-grow: 1; /* O wrapper do dashboard deve crescer para preencher o espaço */
            display: flex; 
            flex-direction: column; 
            <?php endif; ?>
        }

        /* Estilos para o formulário de login (quando $show_dashboard_content é false) */
        .login-container {
            background: #fff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            width: 100%; 
        }
        .login-container img { width: 70px; margin-bottom:10px; }
        .login-container h2 { margin-bottom: 5px; color: #333; font-size:1.8em; }
        .login-container h3 { margin-bottom: 25px; color: #555; font-weight: normal; font-size: 1.1em; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size:1em; }
        .btn-login { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; transition: background-color 0.3s ease; }
        .btn-login:hover { background-color: #0056b3; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9em; text-align:left; }
        .login-link-alt { margin-top: 20px; font-size: 0.9em;}
        .login-link-alt a { color: #007bff; text-decoration: none; }
        .login-link-alt a:hover { text-decoration: underline; }


        /* Estilos para o wrapper do dashboard (quando $show_dashboard_content é true) */
        .dashboard-wrapper {
            width: 100%;
            <?php if ($show_dashboard_content): ?>
            flex-grow: 1; /* Permite que o conteúdo do dashboard preencha o espaço vertical */
            display: flex; 
            flex-direction: column; 
            scroll-padding-top: 100px;
            /* O style.css principal já deve cuidar do layout interno do dashboard */
            <?php endif; ?>
        }
        /* .footer-text não é mais necessário aqui se o dashboard_content tem seu próprio footer fixo */
    </style>
</head>
<body>
    <div class="container-wrapper">
        <?php if ($show_dashboard_content): ?>
            <div class="dashboard-wrapper">
                <?php 
                // dashboard_content.php agora é apenas o fragmento HTML do dashboard
                // Não deve ter suas próprias tags <html>, <head>, <body>
                include_once "dashboard_content.php"; 
                ?>
            </div>
        <?php else: // Mostra o formulário de entrada de senha ?>
            <div class="login-container">
                <img src="/images/anotamed1.png" alt="Logo AnotaMed">
                <h2 style="color: #000000;">anotamed</h2>
                <h3>Entrar como <strong><?php echo htmlspecialchars($username_from_url); ?></strong></h3>

                <?php if(!empty($profile_login_err)): ?>
                    <p class="error-message"><?php echo $profile_login_err; ?></p>
                <?php endif; ?>
                
                <form action="auth.php" method="post">
                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username_from_url); ?>">
                    <input type="hidden" name="login_source" value="profile_url">

                    <div class="form-group">
                        <label for="password">Senha:</label>
                        <input type="password" name="password" id="password" required autofocus>
                    </div>
                    <button type="submit" class="btn-login">Entrar</button>
                </form>
                <p class="login-link-alt">
                    Não é <?php echo htmlspecialchars($username_from_url); ?>? 
                    <a href="login.php">Use outra conta</a>
                    <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && $_SESSION['username'] !== $username_from_url): ?>
                         ou <a href="/<?php echo urlencode($_SESSION['username']); ?>">ir para o seu perfil (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>.
                         Ou <a href="logout.php?redirect_to=<?php echo urlencode($username_from_url); ?>">Sair da conta atual</a> para tentar como <?php echo htmlspecialchars($username_from_url); ?>.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($show_dashboard_content): ?>
        <!-- Scripts do Dashboard carregados no final do body para melhor performance -->
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        <script src="js/script.js"></script> 
    <?php endif; ?>
</body>
</html>