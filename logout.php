<?php
session_start();

$redirect_target_after_logout = "login.php"; // Padrão

// Se um usuário específico foi passado para redirecionar após o logout
// (ex: se o usuário clicou em "Sair para tentar como outro_user")
if (isset($_GET['redirect_to']) && !empty(trim($_GET['redirect_to']))) {
    $username_to_redirect = trim($_GET['redirect_to']);
    // Aqui você pode opcionalmente verificar se $username_to_redirect é um usuário válido
    // antes de construir a URL, mas por simplicidade vamos apenas usá-lo.
    $redirect_target_after_logout = "/" . urlencode($username_to_redirect);
} elseif (isset($_SESSION['username'])) {
    // Opcional: redirecionar para a página de perfil do usuário que acabou de deslogar
    // $redirect_target_after_logout = "/" . urlencode($_SESSION['username']);
}


// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destrói o cookie da sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão.
session_destroy();

// Redireciona para o local desejado
header("Location: " . $redirect_target_after_logout);
exit;
?>