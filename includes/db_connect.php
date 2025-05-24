<?php
// includes/db_connect.php

// Define o caminho para o arquivo de configuração
$config_file = __DIR__ . 
'/config.php
';

// Verifica se o arquivo de configuração existe
if (!file_exists($config_file)) {
    // Em ambiente de produção, logar o erro e mostrar mensagem genérica.
    // Em desenvolvimento, pode ser útil mostrar um erro mais específico.
    error_log("ERRO CRÍTICO: Arquivo de configuração 'config.php' não encontrado em " . __DIR__);
    die("ERRO: Configuração do sistema não encontrada. Por favor, crie o arquivo 'includes/config.php' a partir de 'config.php.example' e defina suas credenciais de banco de dados.");
}

// Inclui o arquivo de configuração
require_once $config_file;

// Verifica se as constantes do banco de dados foram definidas no config.php
if (!defined(\'DB_SERVER
') || !defined(\'DB_USERNAME
') || !defined(\'DB_PASSWORD
') || !defined(\'DB_NAME
')) {
    error_log("ERRO CRÍTICO: Constantes de configuração do banco de dados não definidas em 'config.php'.");
    die("ERRO: Configuração do banco de dados incompleta.");
}

// Tenta conectar ao banco de dados MySQL usando as constantes do config.php
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão
if ($mysqli->connect_error) {
    // Loga o erro detalhado no servidor
    error_log("ERRO: Não foi possível conectar ao banco de dados. " . $mysqli->connect_errno . ": " . $mysqli->connect_error);
    // Mostra uma mensagem genérica para o usuário
    die("ERRO: Problema de conexão com o servidor de dados. Por favor, tente novamente mais tarde ou contate o suporte.");
}

// Define o charset para UTF-8 (importante para caracteres especiais)
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Erro ao definir o charset para utf8mb4: " . $mysqli->error);
    // Considerar se isso deve ser fatal ou não. Geralmente não é, mas pode causar problemas de codificação.
    // die("Erro ao configurar o charset da conexão."); 
}

// A sessão é iniciada nos scripts que a utilizam (login.php, auth.php, etc.)
// Não iniciar a sessão aqui para evitar redundância e possíveis problemas.

?>
