<?php
header(\'Content-Type: application/json\'); // Define o tipo de conteúdo da resposta

require_once \'../includes/db_connect.php\'; // Conexão DB (usa config.php) e session_start()
require_once \'../includes/check_auth.php\'; // Garante que o usuário está logado

$response = [\'success\' => false, \'message\' => \'Ação inválida ou não especificada.\'];
$userId = $_SESSION[\'user_id\']; // Pega o ID do usuário logado

// Função auxiliar para respostas de erro
function send_meds_error($user_message, $log_message = null, $status_code = 400) {
    global $userId;
    http_response_code($status_code);
    if ($log_message) {
        error_log(\"[Meds API Error] User $userId: $log_message\");
    }
    echo json_encode([\'success\' => false, \'message\' => $user_message]);
    exit;
}

// Verifica o método da requisição (esperando POST)
if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
    send_meds_error(\'Método não permitido.\', \'Invalid request method: \' . $_SERVER[\'REQUEST_METHOD\'], 405);
}

// Verifica se a ação foi enviada
if (!isset($_POST[\'action\'])) {
    send_meds_error(\'Ação não especificada.\', \'Action not provided in POST request.\');
}

$action = $_POST[\'action\'];

try {
    switch ($action) {
        case \'search\':
            if (!isset($_POST[\'term\'])) {
                send_meds_error(\'Termo de busca não fornecido.\', \'Search term missing.\');
            }
            $searchTerm = trim($_POST[\'term\']);

            if (mb_strlen($searchTerm) < 2) { // Usar mb_strlen para multi-byte
                // Não é um erro, apenas retorna sucesso com array vazio ou mensagem específica
                $response = [\'success\' => true, \'medications\' => [], \'message\' => \'Termo de busca muito curto (mínimo 2 caracteres).\'];
                break; // Sai do switch, vai para o encode final
            }

            $sql = \"SELECT id, name, description, usage_info, contraindications
                    FROM medications
                    WHERE name LIKE ?
                    ORDER BY name ASC
                    LIMIT 20\"; // Limita resultados para performance

            if ($stmt = $mysqli->prepare($sql)) {
                $likeTerm = \"%\" . $searchTerm . \"%\";
                $stmt->bind_param(\"s\", $likeTerm);

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $medications = $result->fetch_all(MYSQLI_ASSOC);
                    $response = [\'success\' => true, \'medications\' => $medications];
                    // Log opcional de busca bem-sucedida
                    // error_log(\"[Meds API Info] User $userId searched for '$searchTerm', found \" . count($medications) . \" results.\");
                } else {
                    send_meds_error(\'Erro ao buscar medicamentos. Tente novamente.\', \'Execute failed for meds search: \' . $stmt->error, 500);
                }
                $stmt->close();
            } else {
                 send_meds_error(\'Erro interno ao processar a busca. Tente novamente.\', \'Prepare failed for meds search: \' . $mysqli->error, 500);
            }
            break;

        // --- Outras Ações (Exemplo) ---
        /*
        case \'get_details\':
            if (!isset($_POST[\'med_id\']) || !is_numeric($_POST[\'med_id\'])) {
                send_meds_error(\'ID do medicamento inválido.\', \'Invalid or missing med_id for get_details.\');
            }
            $medId = (int)$_POST[\'med_id\'];
            // ... Lógica para buscar detalhes do medicamento $medId ...
            $response = [\'success\' => true, \'details\' => [\'id\' => $medId, \'name\' => \'ExemploMed\"]]; // Dados de exemplo
            break;
        */

        default:
             send_meds_error(\'Ação desconhecida para medicamentos.\', \'Unknown action received: \' . $action);
            break;
    }
} catch (Exception $e) {
    // Captura exceções gerais não previstas
    send_meds_error(\'Ocorreu um erro inesperado.\', \'Unhandled exception in meds_handler: \' . $e->getMessage(), 500);
}

$mysqli->close();
echo json_encode($response);
exit();
?>
