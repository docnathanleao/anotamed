<?php
session_start();
require_once "includes/db_connect.php";

// Limpa mensagens anteriores
unset($_SESSION["register_error"]);
unset($_SESSION["register_success"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $errors = [];

    // Validações
    if (empty($username)) {
        $errors[] = "Por favor, insira um nome de usuário.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Nome de usuário pode conter apenas letras, números e underscore.";
    } else {
        // Verifica se o usuário já existe
        $sql_check = "SELECT id FROM users WHERE username = ?";
        if ($stmt_check = $mysqli->prepare($sql_check)) {
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "Este nome de usuário já está em uso.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Erro ao verificar usuário.";
        }
    }

    if (empty($password)) {
        $errors[] = "Por favor, insira uma senha.";
    } elseif (strlen($password) < 6) {
        $errors[] = "A senha deve ter pelo menos 6 caracteres.";
    }

    if (empty($confirm_password)) {
        $errors[] = "Por favor, confirme a senha.";
    } elseif ($password != $confirm_password) {
        $errors[] = "As senhas não coincidem.";
    }

    // Se não houver erros, insere no banco
    if (empty($errors)) {
        $sql_insert = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            // Cria o hash da senha
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt_insert->bind_param("ss", $username, $hashed_password);

            if ($stmt_insert->execute()) {
                $_SESSION["register_success"] = "Conta criada com sucesso! Você já pode fazer login.";
                header("location: register.php"); // Volta para register para mostrar sucesso
                exit;
            } else {
                $_SESSION["register_error"] = "Oops! Algo deu errado ao criar a conta.";
            }
            $stmt_insert->close();
        } else {
             $_SESSION["register_error"] = "Erro ao preparar a inserção.";
        }
    } else {
        // Junta os erros em uma string
        $_SESSION["register_error"] = implode("<br>", $errors);
    }

    // Fecha a conexão
    $mysqli->close();
    header("location: register.php"); // Volta para register para mostrar erros
    exit;

} else {
    header("location: register.php");
    exit;
}
?>