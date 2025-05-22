<?php
header('Content-Type: application/json'); // Define o tipo de conteúdo da resposta

require_once '../includes/db_connect.php'; // Conexão DB e session_start()
require_once '../includes/check_auth.php'; // Garante que o usuário está logado

$response = ['success' => false, 'message' => 'Ação inválida.'];
$userId = $_SESSION['user_id']; // Embora não usemos o user ID aqui, é bom ter por segurança

// Usaremos POST com 'application/x-www-form-urlencoded' como no JS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'search':
            if (isset($_POST['term'])) {
                $searchTerm = trim($_POST['term']);

                if (strlen($searchTerm) >= 2) { // Mínimo de 2 caracteres para buscar
                    $sql = "SELECT id, name, description, usage_info, contraindications
                            FROM medications
                            WHERE name LIKE ?
                            ORDER BY name ASC
                            LIMIT 20"; // Limita resultados para performance

                    if ($stmt = $mysqli->prepare($sql)) {
                        $likeTerm = "%" . $searchTerm . "%";
                        $stmt->bind_param("s", $likeTerm);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            $medications = $result->fetch_all(MYSQLI_ASSOC);
                            $response = ['success' => true, 'medications' => $medications];
                        } else {
                            $response['message'] = "Erro ao buscar medicamentos: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                         $response['message'] = "Erro ao preparar consulta (search meds): " . $mysqli->error;
                    }
                } else {
                    $response['message'] = "Termo de busca muito curto.";
                }
            } else {
                $response['message'] = "Termo de busca não fornecido.";
            }
            break;

        // Poderia ter outras ações como 'get_details', 'add_med', etc. no futuro

        default:
             $response['message'] = "Ação desconhecida para medicamentos.";
            break;
    }
}

$mysqli->close();
echo json_encode($response);
exit();
?>