<?php
// --- Configuração Inicial ---
header(\'Content-Type: application/json\'); // Define o tipo de conteúdo da resposta SEMPRE PRIMEIRO

// Includes Essenciais
require_once \'../includes/db_connect.php\'; // Assume que define $mysqli e faz session_start()
require_once \'../includes/check_auth.php\'; // Garante que $_SESSION[\'user_id\'] existe

// --- Funções Auxiliares ---

// Função para enviar resposta JSON de erro e sair
function send_notes_error($user_message, $log_message = null, $status_code = 400) {
    global $userId; // Acessa o $userId definido globalmente
    http_response_code($status_code);
    if ($log_message) {
        error_log(\"[Notes API Error] User $userId: $log_message\");
    }
    echo json_encode([\'success\' => false, \'message\' => $user_message]);
    exit;
}

// Função para enviar resposta JSON de sucesso e sair
function send_notes_success($data = [], $message = null) {
    $response = [\'success\' => true];
    if ($message) {
        $response[\'message\'] = $message;
    }
    // Mescla dados adicionais na resposta
    $response = array_merge($response, $data);
    echo json_encode($response);
    exit;
}

// --- Verificações Iniciais ---

// Verifica se o usuário está autenticado (check_auth.php deve garantir isso)
if (!isset($_SESSION[\'user_id\'])) {
    send_notes_error(\'Não autenticado.\', \'User ID not found in session.\', 401);
}
$userId = $_SESSION[\'user_id\']; // Pega o ID do usuário logado

// Decodifica a entrada JSON
$input = json_decode(file_get_contents(\'php://input\'), true);

// Verifica se a entrada JSON é válida e contém a ação
if (!$input || !isset($input[\'action\'])) {
    send_notes_error(\'Requisição inválida ou ação não especificada.\', \'Invalid JSON input or action missing.\');
}

$action = $input[\'action\'];
error_log(\"[Notes API Info] User $userId: Action received: $action\");

// --- Processamento da Ação ---
try {
    switch ($action) {

        // --- Carregar Notas e Categorias --- (Combinado para eficiência)
        case \'load_all\':
            $notes = [];
            $categories = [];

            // Carregar Notas
            $sql_notes = \"SELECT id, title, content, category_id FROM notes WHERE user_id = ? ORDER BY updated_at DESC\";
            if ($stmt_notes = $mysqli->prepare($sql_notes)) {
                $stmt_notes->bind_param(\"i\", $userId);
                if ($stmt_notes->execute()) {
                    $result_notes = $stmt_notes->get_result();
                    while ($row = $result_notes->fetch_assoc()) {
                        $row[\'id\'] = (string)$row[\'id\']; // Garante ID como string
                        $row[\'category_id\'] = (string)$row[\'category_id\']; // Garante ID como string
                        $notes[] = $row;
                    }
                } else {
                    send_notes_error(\"Erro ao buscar notas.\", \"Execute failed for load notes: \" . $stmt_notes->error, 500);
                }
                $stmt_notes->close();
            } else {
                send_notes_error(\"Erro interno ao processar notas.\", \"Prepare failed for load notes: \" . $mysqli->error, 500);
            }

            // Carregar Categorias
            // Usar o nome da coluna de ordem do banco, ex: \'display_order\'
            $sql_cats = \"SELECT id, name, display_order AS `order` FROM categories WHERE user_id = ? ORDER BY `order` ASC, name ASC\";
            if ($stmt_cats = $mysqli->prepare($sql_cats)) {
                $stmt_cats->bind_param(\"i\", $userId);
                if ($stmt_cats->execute()) {
                    $result_cats = $stmt_cats->get_result();
                    while ($row = $result_cats->fetch_assoc()) {
                        $row[\'id\'] = (string)$row[\'id\']; // Garante ID como string
                        $row[\'order\'] = isset($row[\'order\']) ? (int)$row[\'order\'] : 0; // Garante que é int
                        $categories[] = $row;
                    }
                } else {
                    send_notes_error(\"Erro ao buscar categorias.\", \"Execute failed for load categories: \" . $stmt_cats->error, 500);
                }
                $stmt_cats->close();
            } else {
                send_notes_error(\"Erro interno ao processar categorias.\", \"Prepare failed for load categories: \" . $mysqli->error, 500);
            }

            error_log(\"[Notes API Info] User $userId: Load All successful. Notes: \" . count($notes) . \", Categories: \" . count($categories) . \".\");
            send_notes_success([\'notes\' => $notes, \'categories\' => $categories]);
            break;

        // --- Salvar Nota ---
        case \'save\':
            if (!isset($input[\'note\']) || !is_array($input[\'note\'])) {
                send_notes_error(\"Dados da nota inválidos.\", \"Invalid or missing \'note\' data in input.\");
            }
            $noteData = $input[\'note\'];
            $noteId = isset($noteData[\'id\']) && is_numeric($noteData[\'id\']) ? (int)$noteData[\'id\'] : null;
            $title = isset($noteData[\'title\']) ? trim($noteData[\'title\']) : \'Nota sem título\';
            $content = $noteData[\'content\'] ?? \'\';
            $categoryId = isset($noteData[\'category_id\']) && is_numeric($noteData[\'category_id\']) ? (int)$noteData[\'category_id\'] : null;

            if (empty($title)) $title = \"Nota sem título\"; // Título padrão
            if (mb_strlen($title) > 255) $title = mb_substr($title, 0, 255); // Limita tamanho do título

            if ($categoryId === null || $categoryId <= 0) {
                send_notes_error(\"ID da categoria inválido ou não fornecido.\", \"Invalid category ID ($categoryId) for save note.\");
            }

            // Verifica se a categoria pertence ao usuário
            $catCheckStmt = $mysqli->prepare(\"SELECT id FROM categories WHERE id = ? AND user_id = ?\");
            $catCheckStmt->bind_param(\"ii\", $categoryId, $userId);
            $catCheckStmt->execute();
            $catCheckStmt->store_result();
            $category_exists = $catCheckStmt->num_rows > 0;
            $catCheckStmt->close();
            if (!$category_exists) {
                send_notes_error(\"Categoria inválida ou não pertence a você.\", \"Category $categoryId not found or not owned by user $userId.\");
            }

            if ($noteId) { // UPDATE
                error_log(\"[Notes API Info] User $userId: Attempting UPDATE Note ID: $noteId, Cat ID: $categoryId\");
                $sql = \"UPDATE notes SET title = ?, content = ?, category_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?\";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param(\"ssiii\", $title, $content, $categoryId, $noteId, $userId);
                    if ($stmt->execute()) {
                        $affectedRows = $stmt->affected_rows;
                        $stmt->close();
                        $message = $affectedRows > 0 ? \'Nota atualizada.\' : \'Nenhuma alteração detectada.\';
                        error_log(\"[Notes API Info] User $userId: Update Note $noteId successful ($affectedRows affected).\");
                        send_notes_success([\'note_id\' => (string)$noteId], $message);
                    } else {
                        $errorMsg = $stmt->error;
                        $stmt->close();
                        send_notes_error(\"Erro ao atualizar nota.\", \"Execute failed for update note $noteId: $errorMsg\", 500);
                    }
                } else {
                    send_notes_error(\"Erro interno ao atualizar nota.\", \"Prepare failed for update note $noteId: \" . $mysqli->error, 500);
                }
            } else { // INSERT
                error_log(\"[Notes API Info] User $userId: Attempting INSERT New Note, Cat ID: $categoryId\");
                $sql = \"INSERT INTO notes (user_id, category_id, title, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())\";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param(\"iiss\", $userId, $categoryId, $title, $content);
                    if ($stmt->execute()) {
                        $newNoteId = $mysqli->insert_id;
                        $stmt->close();
                        error_log(\"[Notes API Info] User $userId: Insert Note successful, New ID: $newNoteId\");
                        send_notes_success([\'note_id\' => (string)$newNoteId], \'Nota criada com sucesso.\');
                    } else {
                        $errorMsg = $stmt->error;
                        $stmt->close();
                        send_notes_error(\"Erro ao criar nota.\", \"Execute failed for insert note: $errorMsg\", 500);
                    }
                } else {
                    send_notes_error(\"Erro interno ao criar nota.\", \"Prepare failed for insert note: \" . $mysqli->error, 500);
                }
            }
            break;

        // --- Deletar Nota ---
        case \'delete\':
            if (!isset($input[\'note_id\']) || !is_numeric($input[\'note_id\'])) {
                send_notes_error(\"ID da nota inválido.\", \"Invalid or missing note_id for delete.\");
            }
            $noteIdToDelete = (int)$input[\'note_id\'];
            error_log(\"[Notes API Info] User $userId: Attempting DELETE Note ID: $noteIdToDelete\");

            $sql = \"DELETE FROM notes WHERE id = ? AND user_id = ?\";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param(\"ii\", $noteIdToDelete, $userId);
                if ($stmt->execute()) {
                    $affectedRows = $stmt->affected_rows;
                    $stmt->close();
                    if ($affectedRows > 0) {
                        error_log(\"[Notes API Info] User $userId: Delete Note $noteIdToDelete successful.\");
                        send_notes_success([], \'Nota excluída com sucesso.\');
                    } else {
                        error_log(\"[Notes API Warn] User $userId: Delete Note $noteIdToDelete - Not found or not owned.\");
                        // Considerar 404 se a nota não existe, ou 200 OK se a exclusão idempotente é desejada
                        send_notes_success([], \'Nota não encontrada ou já excluída.\');
                    }
                } else {
                    $errorMsg = $stmt->error;
                    $stmt->close();
                    // Verificar erro de chave estrangeira, se aplicável
                    if (strpos($errorMsg, \'foreign key constraint fails\') !== false) {
                         send_notes_error(\'Não é possível excluir: verifique dependências.\', \"Execute failed for delete note $noteIdToDelete (FK constraint?): $errorMsg\", 409); // 409 Conflict
                    } else {
                         send_notes_error(\"Erro ao excluir nota.\", \"Execute failed for delete note $noteIdToDelete: $errorMsg\", 500);
                    }
                }
            } else {
                send_notes_error(\"Erro interno ao excluir nota.\", \"Prepare failed for delete note $noteIdToDelete: \" . $mysqli->error, 500);
            }
            break;

        // --- Criar Categoria ---
        case \'create_category\':
            if (!isset($input[\'name\']) || empty(trim($input[\'name\']))) {
                send_notes_error(\'Nome da categoria inválido.\', \"Invalid or missing category name for create.\");
            }
            $category_name = trim($input[\'name\']);
            $order = isset($input[\'order\']) && is_numeric($input[\'order\']) ? (int)$input[\'order\'] : 0;
            error_log(\"[Notes API Info] User $userId: Attempting CREATE CATEGORY: Name=\'$category_name\', Order=$order\");

            if (mb_strlen($category_name) > 150) {
                send_notes_error(\'Nome da categoria muito longo (máx 150 caracteres).\', \"Category name too long.\");
            }

            // Verifica nome duplicado
            $checkStmt = $mysqli->prepare(\"SELECT id FROM categories WHERE user_id = ? AND name = ?\");
            $checkStmt->bind_param(\"is\", $userId, $category_name);
            $checkStmt->execute();
            $checkStmt->store_result();
            $exists = $checkStmt->num_rows > 0;
            $checkStmt->close();
            if ($exists) {
                send_notes_error(\'Você já possui uma categoria com este nome.\', \"Duplicate category name for user $userId.\", 409); // 409 Conflict
            }

            // Insere a nova categoria (usar nome da coluna de ordem do banco, ex: \'display_order\')
            $insertStmt = $mysqli->prepare(\"INSERT INTO categories (user_id, name, display_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())\");
            $insertStmt->bind_param(\"isi\", $userId, $category_name, $order);
            if ($insertStmt->execute()) {
                $new_category_id = $mysqli->insert_id;
                $insertStmt->close();
                $newCategoryData = [
                    \'id\' => (string)$new_category_id,
                    \'name\' => $category_name,
                    \'order\' => $order
                ];
                error_log(\"[Notes API Info] User $userId: Create Category successful, New ID: $new_category_id\");
                send_notes_success([\'category\' => $newCategoryData], \'Categoria criada com sucesso!\');
            } else {
                $errorMsg = $insertStmt->error;
                $insertStmt->close();
                send_notes_error(\"Erro ao criar categoria.\", \"Execute failed for insert category: $errorMsg\", 500);
            }
            break;

        // --- Renomear/Atualizar Categoria ---
        case \'update_category\':
            if (!isset($input[\'category_id\']) || !is_numeric($input[\'category_id\'])) {
                send_notes_error(\'ID da categoria inválido.\', \"Invalid or missing category_id for update.\");
            }
            if (!isset($input[\'name\']) || empty(trim($input[\'name\']))) {
                send_notes_error(\'Nome da categoria inválido.\', \"Invalid or missing category name for update.\");
            }
            $categoryId = (int)$input[\'category_id\'];
            $newName = trim($input[\'name\']);
            error_log(\"[Notes API Info] User $userId: Attempting UPDATE CATEGORY ID: $categoryId, New Name: \'$newName\\'\");

            if (mb_strlen($newName) > 150) {
                send_notes_error(\'Nome da categoria muito longo (máx 150 caracteres).\', \"Category name too long for update.\");
            }

            // Verifica se o novo nome já existe para OUTRA categoria do mesmo usuário
            $checkStmt = $mysqli->prepare(\"SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ?\");
            $checkStmt->bind_param(\"isi\", $userId, $newName, $categoryId);
            $checkStmt->execute();
            $checkStmt->store_result();
            $duplicateExists = $checkStmt->num_rows > 0;
            $checkStmt->close();
            if ($duplicateExists) {
                send_notes_error(\'Você já possui outra categoria com este nome.\', \"Duplicate category name exists for user $userId on update.\", 409);
            }

            // Atualiza o nome da categoria
            $updateStmt = $mysqli->prepare(\"UPDATE categories SET name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?\");
            $updateStmt->bind_param(\"sii\", $newName, $categoryId, $userId);
            if ($updateStmt->execute()) {
                $affectedRows = $updateStmt->affected_rows;
                $updateStmt->close();
                if ($affectedRows > 0) {
                    error_log(\"[Notes API Info] User $userId: Update Category $categoryId successful.\");
                    send_notes_success([\'category\' => [\'id\' => (string)$categoryId, \'name\' => $newName]], \'Categoria renomeada com sucesso.\');
                } else {
                    error_log(\"[Notes API Warn] User $userId: Update Category $categoryId - Not found, not owned, or name unchanged.\");
                    // Pode ser 404 se não encontrada/pertence, ou 200 OK se nome igual
                    send_notes_success([\'category\' => [\'id\' => (string)$categoryId, \'name\' => $newName]], \'Nenhuma alteração necessária ou categoria não encontrada.\');
                }
            } else {
                $errorMsg = $updateStmt->error;
                $updateStmt->close();
                send_notes_error(\"Erro ao renomear categoria.\", \"Execute failed for update category $categoryId: $errorMsg\", 500);
            }
            break;

        // --- Deletar Categoria ---
        case \'delete_category\':
            if (!isset($input[\'category_id\']) || !is_numeric($input[\'category_id\'])) {
                send_notes_error(\'ID da categoria inválido.\', \"Invalid or missing category_id for delete.\");
            }
            $categoryIdToDelete = (int)$input[\'category_id\'];
            error_log(\"[Notes API Info] User $userId: Attempting DELETE CATEGORY ID: $categoryIdToDelete\");

            // *** IMPORTANTE: Decidir o que fazer com as notas da categoria ***
            // Opção 1: Excluir notas junto (CASCADE no DB ou DELETE manual aqui)
            // Opção 2: Impedir exclusão se houver notas (verificar antes)
            // Opção 3: Mover notas para uma categoria padrão/sem categoria (requer lógica adicional)

            // Implementando Opção 2: Impedir se houver notas
            $noteCheckStmt = $mysqli->prepare(\"SELECT COUNT(*) as note_count FROM notes WHERE category_id = ? AND user_id = ?\");
            $noteCheckStmt->bind_param(\"ii\", $categoryIdToDelete, $userId);
            $noteCheckStmt->execute();
            $noteCheckResult = $noteCheckStmt->get_result()->fetch_assoc();
            $noteCheckStmt->close();

            if ($noteCheckResult && $noteCheckResult[\'note_count\'] > 0) {
                send_notes_error(\"Não é possível excluir a categoria pois ela contém notas. Exclua ou mova as notas primeiro.\", \"Attempted to delete category $categoryIdToDelete with existing notes.\", 409); // 409 Conflict
            }

            // Se não há notas, prossegue com a exclusão da categoria
            $sql = \"DELETE FROM categories WHERE id = ? AND user_id = ?\";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param(\"ii\", $categoryIdToDelete, $userId);
                if ($stmt->execute()) {
                    $affectedRows = $stmt->affected_rows;
                    $stmt->close();
                    if ($affectedRows > 0) {
                        error_log(\"[Notes API Info] User $userId: Delete Category $categoryIdToDelete successful.\");
                        send_notes_success([], \'Categoria excluída com sucesso.\');
                    } else {
                        error_log(\"[Notes API Warn] User $userId: Delete Category $categoryIdToDelete - Not found or not owned.\");
                        send_notes_success([], \'Categoria não encontrada ou já excluída.\');
                    }
                } else {
                    $errorMsg = $stmt->error;
                    $stmt->close();
                    send_notes_error(\"Erro ao excluir categoria.\", \"Execute failed for delete category $categoryIdToDelete: $errorMsg\", 500);
                }
            } else {
                send_notes_error(\"Erro interno ao excluir categoria.\", \"Prepare failed for delete category $categoryIdToDelete: \" . $mysqli->error, 500);
            }
            break;

        // --- Atualizar Ordem das Categorias ---
        case \'update_category_order\':
            if (!isset($input[\'order\']) || !is_array($input[\'order\'])) {
                send_notes_error(\'Dados de ordenação inválidos.\', \"Invalid or missing order array for update_category_order.\");
            }
            $orderedIds = $input[\'order\'];
            error_log(\"[Notes API Info] User $userId: Attempting UPDATE CATEGORY ORDER: \" . implode(\",\", $orderedIds));

            // Inicia transação para garantir atomicidade
            $mysqli->begin_transaction();
            $success = true;
            // Usar nome da coluna de ordem do banco, ex: \'display_order\'
            $sql = \"UPDATE categories SET display_order = ?, updated_at = NOW() WHERE id = ? AND user_id = ?\";
            if ($stmt = $mysqli->prepare($sql)) {
                foreach ($orderedIds as $index => $categoryId) {
                    if (!is_numeric($categoryId)) continue; // Ignora IDs inválidos
                    $orderValue = $index; // Ordem baseada no índice do array
                    $catIdInt = (int)$categoryId;
                    $stmt->bind_param(\"iii\", $orderValue, $catIdInt, $userId);
                    if (!$stmt->execute()) {
                        $success = false;
                        $errorMsg = $stmt->error;
                        error_log(\"[Notes API Error] User $userId: Execute failed during category order update for ID $catIdInt: $errorMsg\");
                        break; // Interrompe o loop em caso de erro
                    }
                }
                $stmt->close();
            } else {
                $success = false;
                error_log(\"[Notes API Error] User $userId: Prepare failed for category order update: \" . $mysqli->error);
            }

            // Finaliza a transação
            if ($success) {
                $mysqli->commit();
                error_log(\"[Notes API Info] User $userId: Update Category Order successful.\");
                send_notes_success([], \'Ordem das categorias atualizada.\');
            } else {
                $mysqli->rollback();
                send_notes_error(\"Erro ao atualizar a ordem das categorias.\", null, 500); // Log já foi feito
            }
            break;

        // --- Atualizar Ordem das Notas Dentro de uma Categoria ---
        case \'update_note_order\':
             if (!isset($input[\'category_id\']) || !is_numeric($input[\'category_id\'])) {
                 send_notes_error(\'ID da categoria inválido.\', \"Invalid or missing category_id for update_note_order.\");
             }
             if (!isset($input[\'order\']) || !is_array($input[\'order\'])) {
                 send_notes_error(\'Dados de ordenação de notas inválidos.\', \"Invalid or missing order array for update_note_order.\");
             }
             $categoryIdForNoteOrder = (int)$input[\'category_id\'];
             $orderedNoteIds = $input[\'order\'];
             error_log(\"[Notes API Info] User $userId: Attempting UPDATE NOTE ORDER for Cat ID $categoryIdForNoteOrder: \" . implode(\",\", $orderedNoteIds));

             // TODO: Implementar a lógica de atualização da ordem das notas.
             // Isso geralmente requer uma coluna de ordem na tabela \'notes\' (ex: \'note_order\').
             // Similar à atualização da ordem das categorias, iterar sobre $orderedNoteIds
             // e atualizar a coluna \'note_order\' para cada nota, garantindo que pertençam
             // ao $userId e $categoryIdForNoteOrder corretos.
             // Exemplo (assumindo coluna \'note_order\'):
             /*
             $mysqli->begin_transaction();
             $success = true;
             $sql = \"UPDATE notes SET note_order = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND category_id = ?\";
             if ($stmt = $mysqli->prepare($sql)) {
                 foreach ($orderedNoteIds as $index => $noteId) {
                     if (!is_numeric($noteId)) continue;
                     $orderValue = $index;
                     $noteIdInt = (int)$noteId;
                     $stmt->bind_param(\"iiii\", $orderValue, $noteIdInt, $userId, $categoryIdForNoteOrder);
                     if (!$stmt->execute()) {
                         $success = false;
                         error_log(\"[Notes API Error] User $userId: Execute failed during note order update for Note ID $noteIdInt: \" . $stmt->error);
                         break;
                     }
                 }
                 $stmt->close();
             } else {
                 $success = false;
                 error_log(\"[Notes API Error] User $userId: Prepare failed for note order update: \" . $mysqli->error);
             }

             if ($success) {
                 $mysqli->commit();
                 error_log(\"[Notes API Info] User $userId: Update Note Order successful for Cat ID $categoryIdForNoteOrder.\");
                 send_notes_success([], \'Ordem das notas atualizada.\');
             } else {
                 $mysqli->rollback();
                 send_notes_error(\"Erro ao atualizar a ordem das notas.\", null, 500);
             }
             */
             // Placeholder - Remover quando implementar
             send_notes_error(\"Funcionalidade de ordenar notas ainda não implementada no backend.\", null, 501); // 501 Not Implemented

             break;

        default:
            send_notes_error(\"Ação desconhecida.\", \"Unknown action received: $action\");
            break;
    }
} catch (Exception $e) {
    // Captura exceções gerais não previstas
    send_notes_error(\"Ocorreu um erro inesperado no servidor.\", \"Unhandled exception in notes_handler: \" . $e->getMessage(), 500);
}

// Fecha a conexão (se ainda não fechada)
if ($mysqli) {
    $mysqli->close();
}

// Se chegou aqui sem sair, algo deu errado ou a ação não enviou resposta
echo json_encode($response); // Envia a resposta padrão de erro
exit();
?>
