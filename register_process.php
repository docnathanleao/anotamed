<?php
session_start();
require_once "includes/db_connect.php"; // Uses config.php now

// Function to set error/success message in session and redirect
function handle_register_redirect($message, $is_success = false, $log_message = null) {
    if ($log_message) {
        error_log("[Register Process] User (".($_SESSION["user_id"] ?? "guest")."): " . $log_message);
    }
    if ($is_success) {
        unset($_SESSION["register_error"]);
        $_SESSION["register_success"] = $message;
    } else {
        unset($_SESSION["register_success"]);
        // Combine multiple errors if $message is an array
        $_SESSION["register_error"] = is_array($message) ? implode("<br>", $message) : $message;
    }
    header("location: register.php");
    exit;
}

// Clear previous messages
unset($_SESSION["register_error"]);
unset($_SESSION["register_success"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');
    $errors = [];

    // --- Validations ---
    if (empty($username)) {
        $errors[] = "Por favor, insira um nome de usuário.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) { // Added length constraints
        $errors[] = "Nome de usuário deve ter entre 3 e 30 caracteres e conter apenas letras, números e underscore.";
    } else {
        // Check if username already exists using prepared statement
        $sql_check = "SELECT id FROM users WHERE username = ?";
        if ($stmt_check = $mysqli->prepare($sql_check)) {
            $stmt_check->bind_param("s", $username);
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $errors[] = "Este nome de usuário já está em uso.";
                }
            } else {
                 // Log DB error, show generic error to user
                 error_log("[Register Process] Error executing username check: " . $stmt_check->error);
                 $errors[] = "Erro ao verificar a disponibilidade do nome de usuário. Tente novamente.";
            }
            $stmt_check->close();
        } else {
            // Log DB error, show generic error to user
            error_log("[Register Process] Error preparing username check: " . $mysqli->error);
            $errors[] = "Erro interno ao verificar o nome de usuário. Tente novamente.";
        }
    }

    if (empty($password)) {
        $errors[] = "Por favor, insira uma senha.";
    } elseif (strlen($password) < 8) { // Increased minimum length
        $errors[] = "A senha deve ter pelo menos 8 caracteres.";
        // Add more password complexity rules if desired (e.g., require numbers, symbols, etc.)
    }

    if (empty($confirm_password)) {
        $errors[] = "Por favor, confirme a senha.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "As senhas não coincidem.";
    }

    // --- Process Registration or Redirect with Errors ---
    if (!empty($errors)) {
        handle_register_redirect($errors, false, "Validation errors during registration for username: " . $username);
    }

    // --- Insert User into Database ---
    $sql_insert = "INSERT INTO users (username, password_hash, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        // Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
             handle_register_redirect("Ocorreu um erro crítico ao processar sua senha.", false, "Password hashing failed for username: " . $username);
        }

        $stmt_insert->bind_param("ss", $username, $hashed_password);

        if ($stmt_insert->execute()) {
            // Registration successful
            $log_msg = "User registered successfully: " . $username;
            handle_register_redirect("Conta criada com sucesso! Você já pode fazer login.", true, $log_msg);
        } else {
            // Insertion failed
            $log_msg = "Error executing user insertion for username " . $username . ": " . $stmt_insert->error;
            handle_register_redirect("Oops! Algo deu errado ao criar a conta. Tente novamente mais tarde.", false, $log_msg);
        }
        $stmt_insert->close();
    } else {
        // Prepare failed
        $log_msg = "Error preparing user insertion: " . $mysqli->error;
        handle_register_redirect("Erro interno ao tentar criar a conta. Tente novamente mais tarde.", false, $log_msg);
    }

    $mysqli->close();

} else {
    // Invalid request method
    header("location: register.php");
    exit;
}
?>
