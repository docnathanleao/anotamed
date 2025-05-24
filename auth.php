<?php
session_start();
require_once "includes/db_connect.php"; // Now uses config.php

$login_source = isset($_POST["login_source"]) && $_POST["login_source"] == "profile_url" ? "profile" : "regular";
$username_for_redirect_on_error = isset($_POST["username"]) ? trim($_POST["username"]) : "";

// Determine redirect URL based on login source
$redirect_url_on_error = "login.php"; // Default redirect
if ($login_source == "profile" && !empty($username_for_redirect_on_error)) {
    $redirect_url_on_error = "/" . urlencode($username_for_redirect_on_error); // Redirect to profile URL
}

// Function to set error message in session and redirect
function handle_auth_error($message, $log_message = null, $is_profile_source = false, $redirect_url = "login.php") {
    if ($log_message) {
        error_log("[Auth Error] User (".($_SESSION["user_id"] ?? "guest")."): " . $log_message);
    }
    if ($is_profile_source) {
        $_SESSION["profile_login_error"] = $message;
    } else {
        $_SESSION["login_error"] = $message;
    }
    header("location: " . $redirect_url);
    exit;
}

// Clear previous errors based on source
if ($login_source == "profile") {
    unset($_SESSION["profile_login_error"]);
} else {
    unset($_SESSION["login_error"]);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["username"]) && isset($_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Validate input
    if (empty($username) || empty($password)) {
        handle_auth_error("Por favor, preencha o usuário e a senha.", null, $login_source == "profile", $redirect_url_on_error);
    }

    // Prepare SQL statement
    $sql = "SELECT id, username, password_hash FROM users WHERE username = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $param_username);
        $param_username = $username;

        // Execute statement
        if ($stmt->execute()) {
            $stmt->store_result();

            // Check if user exists
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username_db, $hashed_password);
                if ($stmt->fetch()) {
                    // Verify password
                    if (password_verify($password, $hashed_password)) {
                        // Password is correct, start a new session
                        session_regenerate_id(true); // Prevent session fixation
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $id;
                        $_SESSION["username"] = $username_db;

                        // Clear any potential error messages from previous attempts
                        unset($_SESSION["login_error"]);
                        unset($_SESSION["profile_login_error"]);

                        // Redirect to user profile page
                        header("location: /" . urlencode($username_db));
                        exit;
                    } else {
                        // Invalid password
                        $error_message_user = "Usuário ou senha inválidos.";
                        $error_message_log = "Invalid password attempt for user: " . $username;
                        handle_auth_error($error_message_user, $error_message_log, $login_source == "profile", $redirect_url_on_error);
                    }
                } else {
                     // Should not happen if num_rows is 1, but handle defensively
                     handle_auth_error("Ocorreu um erro ao processar suas informações. Tente novamente.", "Failed to fetch user data after successful query for user: " . $username, $login_source == "profile", $redirect_url_on_error);
                }
            } else {
                // User not found
                $error_message_user = "Usuário ou senha inválidos.";
                $error_message_log = "User not found: " . $username;
                handle_auth_error($error_message_user, $error_message_log, $login_source == "profile", $redirect_url_on_error);
            }
        } else {
            // Execute failed
            $error_message_user = "Ocorreu um erro ao tentar autenticar. Tente novamente mais tarde.";
            $error_message_log = "SQL execute error for user " . $username . ": " . $stmt->error;
            handle_auth_error($error_message_user, $error_message_log, $login_source == "profile", $redirect_url_on_error);
        }
        $stmt->close();
    } else {
        // Prepare failed
        $error_message_user = "Ocorreu um erro interno no servidor. Tente novamente mais tarde.";
        $error_message_log = "SQL prepare error: " . $mysqli->error;
        handle_auth_error($error_message_user, $error_message_log, $login_source == "profile", $redirect_url_on_error);
    }
    $mysqli->close();
} else {
    // Invalid request method or missing parameters
    $error_message_user = "Acesso inválido ao script de autenticação.";
    $error_message_log = "Invalid access to auth.php (Method: " . $_SERVER["REQUEST_METHOD"] . ", Params: " . http_build_query($_POST) . ")";
    handle_auth_error($error_message_user, $error_message_log, $login_source == "profile", $redirect_url_on_error);
}
?>
