<?php
// Configurações do Banco de Dados (XAMPP Padrão)
define('DB_SERVER', 'sql103.infinityfree.com');
define('DB_USERNAME', 'if0_38812525');
define('DB_PASSWORD', 'I96097579e');
define('DB_NAME', 'if0_38812525_datacenter'); // Escolha um nome para seu banco de dados

// Tenta conectar ao banco de dados MySQL
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão
if($mysqli === false){
    // Em um ambiente de produção, você não exibiria o erro diretamente.
    // Você o registraria em um arquivo de log e mostraria uma mensagem genérica.
    error_log("ERRO: Não foi possível conectar ao banco de dados. " . $mysqli->connect_error);
    die("ERRO: Problema de conexão com o servidor. Por favor, tente novamente mais tarde.");
}

// Define o charset para UTF-8 (importante para caracteres especiais)
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Erro ao definir o charset para utf8mb4: " . $mysqli->error);
    // die("Erro ao configurar o charset."); // Opcional, pode não ser fatal
}

// A sessão é iniciada no topo de cada script que a utiliza (login.php, auth.php, password_entry.php, etc.)
// Não é estritamente necessário iniciar aqui, e pode ser redundante.
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
?>