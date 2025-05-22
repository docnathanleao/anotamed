<?php
session_start();
require_once "includes/db_connect.php";

$login_source = isset($_POST['login_source']) && $_POST['login_source'] == 'profile_url' ? 'profile' : 'regular';
$username_for_redirect_on_error = isset($_POST['username']) ? trim($_POST['username']) : '';

// Limpa erros anteriores com base na origem
if ($login_source == 'profile') {
    unset($_SESSION["profile_login_error"]);
} else {
    unset($_SESSION["login_error"]);
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])){
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $redirect_url_on_error = "login.php"; // Padrão
    if ($login_source == 'profile' && !empty($username_for_redirect_on_error)) {
        $redirect_url_on_error = "/" . urlencode($username_for_redirect_on_error); // Vai para anotamed.com/username
    }

    if(empty($username) || empty($password)){
        $error_message = "Por favor, preencha o usuário e a senha.";
        // ... (resto da lógica de erro como você tinha, usando $_SESSION["profile_login_error"] ou $_SESSION["login_error"]) ...
        if ($login_source == 'profile') {
            $_SESSION["profile_login_error"] = $error_message;
        } else {
            $_SESSION["login_error"] = $error_message;
        }
        header("location: " . $redirect_url_on_error);
        exit;
    }

    $sql = "SELECT id, username, password_hash FROM users WHERE username = ?";
    if($stmt = $mysqli->prepare($sql)){
        $stmt->bind_param("s", $param_username);
        $param_username = $username;
        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($id, $username_db, $hashed_password);
                if($stmt->fetch()){
                    if(password_verify($password, $hashed_password)){
                        session_regenerate_id(true); 
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $id;
                        $_SESSION["username"] = $username_db;
                        unset($_SESSION["login_error"]);
                        unset($_SESSION["profile_login_error"]);

                        // ***** MUDANÇA PRINCIPAL AQUI *****
                        // Redireciona para a URL do perfil do usuário (anotamed.com/username)
                        header("location: /" . urlencode($username_db));
                        exit;
                    } else{
                        $error_message = "Senha inválida para o usuário \"".htmlspecialchars($username)."\".";
                        // ... (resto da lógica de erro de senha) ...
                        if ($login_source == 'profile') {
                            $_SESSION["profile_login_error"] = $error_message;
                        } else {
                            $_SESSION["login_error"] = "Usuário ou senha inválidos."; 
                        }
                        header("location: " . $redirect_url_on_error);
                        exit;
                    }
                }
            } else{
                $error_message = "Usuário \"".htmlspecialchars($username)."\" não encontrado.";
                // ... (resto da lógica de usuário não encontrado) ...
                 if ($login_source == 'profile') {
                    $_SESSION["profile_login_error"] = $error_message; // Mostra erro na página de perfil
                } else {
                    $_SESSION["login_error"] = "Usuário ou senha inválidos.";
                }
                header("location: " . $redirect_url_on_error);
                exit;
            }
        } else{
            $error_message = "Oops! Algo deu errado com a consulta.";
            // ... (resto da lógica de erro de execução) ...
            if ($login_source == 'profile') {
                $_SESSION["profile_login_error"] = $error_message;
            } else {
                $_SESSION["login_error"] = $error_message;
            }
            header("location: " . $redirect_url_on_error);
            exit;
        }
        $stmt->close();
    } else {
         $error_message = "Erro ao preparar a consulta.";
         // ... (resto da lógica de erro de preparação) ...
         if ($login_source == 'profile') {
            $_SESSION["profile_login_error"] = $error_message;
        } else {
            $_SESSION["login_error"] = $error_message;
        }
         header("location: " . $redirect_url_on_error);
         exit;
    }
    $mysqli->close();
} else {
    // ... (seu tratamento de erro para acesso inválido) ...
    $error_message = "Acesso inválido ao script de autenticação.";
    $redirect_url = "login.php"; 
    if (isset($_POST['login_source']) && $_POST['login_source'] == 'profile_url' && !empty($username_for_redirect_on_error)) {
        $_SESSION["profile_login_error"] = $error_message;
        $redirect_url = "/" . urlencode($username_for_redirect_on_error);
    } else {
        $_SESSION["login_error"] = $error_message;
    }
    header("location: " . $redirect_url);
    exit;
}
?>