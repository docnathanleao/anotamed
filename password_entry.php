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
    $profile_login_err = htmlspecialchars($_SESSION["profile_login_error"], ENT_QUOTES, \'UTF-8\');
    unset($_SESSION["profile_login_error"]);
}

if (isset($_GET[\"username\"])) {
    $username_from_url = trim($_GET[\"username\"]);

    if (empty($username_from_url)) {
        header(\"location: login.php\"); // Redireciona se username da URL estiver vazio
        exit;
    }

    $sql_check = \"SELECT id, username FROM users WHERE username = ?\";
    if ($stmt_check = $mysqli->prepare($sql_check)) {
        $stmt_check->bind_param(\"s\", $param_username_check);
        $param_username_check = $username_from_url;
        if ($stmt_check->execute()) {
            $stmt_check->store_result();
            if ($stmt_check->num_rows == 1) {
                $user_exists_in_db = true;
                $page_title = htmlspecialchars($username_from_url) . \" - AnotaMed\";
            }
        }
        $stmt_check->close();
    }
}

if (!$user_exists_in_db) {
    $_SESSION[\"login_error\"] = \"Usuário \\\"\" . htmlspecialchars($username_from_url) . \"\\\" não encontrado ou URL inválida.\";
    header(\"location: login.php\");
    exit;
}

// Verifica se o usuário logado é o mesmo da URL
if (isset($_SESSION[\"loggedin\"]) && $_SESSION[\"loggedin\"] === true) {
    if ($_SESSION[\"username\"] === $username_from_url) {
        $show_dashboard_content = true;
    }
}

// Define a classe do body com base no conteúdo a ser exibido
$body_class = $show_dashboard_content ? \"dashboard-page\" : \"auth-page\";

?>
<!DOCTYPE html>
<html lang=\"pt-br\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS Principal -->
    <link rel=\"stylesheet\" href=\"/css/style.css\"> 
    <!-- Fontes -->
    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
    <link href=\"https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap\" rel=\"stylesheet\">
    <link href=\"https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap\" rel=\"stylesheet">
    <link rel=\"icon\" type=\"image/png\" href=\"/images/icon.png\">

    <?php if ($show_dashboard_content): ?>
        <!-- Dependências CSS específicas do Dashboard (Font Awesome) -->
        <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php endif; ?>

    <!-- REMOVIDO: Bloco <style> interno -->

</head>
<body class=\"<?php echo $body_class; ?>\">

    <?php if ($show_dashboard_content): ?>
        <div class=\"dashboard-wrapper\"> 
            <?php 
            // Inclui apenas o conteúdo HTML do dashboard
            include_once \"dashboard_content.php\"; 
            ?>
        </div>
    <?php else: // Mostra o formulário de entrada de senha (usando classes de autenticação) ?>
        <div class=\"auth-main-content-area\">
            <div class=\"auth-container\"> 
                <img src=\"/images/anotamed1.png\" alt=\"Logo AnotaMed\" class=\"logo\">
                <h2 class=\"app-title\">anotamed</h2>
                <h3>Entrar como <strong><?php echo htmlspecialchars($username_from_url); ?></strong></h3>

                <?php if(!empty($profile_login_err)): ?>
                    <p class=\"message-display error-message\"><?php echo $profile_login_err; ?></p>
                <?php endif; ?>
                
                <form action=\"auth.php\" method=\"post\" novalidate>
                    <input type=\"hidden\" name=\"username\" value=\"<?php echo htmlspecialchars($username_from_url); ?>\">
                    <input type=\"hidden\" name=\"login_source\" value=\"profile_url\">

                    <div class=\"form-group\">
                        <label for=\"password\">Senha:</label>
                        <input type=\"password\" name=\"password\" id=\"password\" required autofocus autocomplete=\"current-password\">
                    </div>
                    <button type=\"submit\" class=\"btn-auth btn-login\">Entrar</button> 
                </form>
                <p class=\"auth-link\"> 
                    Não é <?php echo htmlspecialchars($username_from_url); ?>? 
                    <a href=\"login.php\">Use outra conta</a>
                    <?php // Link para ir para o próprio perfil se já estiver logado com outra conta
                    if(isset($_SESSION[\"loggedin\"]) && $_SESSION[\"loggedin\"] === true && $_SESSION[\"username\"] !== $username_from_url): ?>
                         ou <a href=\"/<?php echo urlencode($_SESSION[\"username\"]); ?>\">ir para o seu perfil (<?php echo htmlspecialchars($_SESSION[\"username\"]); ?>)</a>.
                         Ou <a href=\"logout.php?redirect_to=<?php echo urlencode($username_from_url); ?>\">Sair da conta atual</a> para tentar como <?php echo htmlspecialchars($username_from_url); ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <footer class=\"auth-footer\"> 
            <span class=\"writing-effect\">synamed</span>
        </footer>
    <?php endif; ?>

    <?php if ($show_dashboard_content): ?>
        <!-- Scripts do Dashboard -->
        <script src=\"https://cdn.jsdelivr.net/npm/marked/marked.min.js\"></script>
        <script src=\"/js/script.js\"></script> 
    <?php endif; ?>
</body>
</html>
