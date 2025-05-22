<?php
// --- Configuração Inicial ---
header('Content-Type: application/json'); // Define o tipo de conteúdo da resposta SEMPRE PRIMEIRO

// Includes Essenciais
require_once '../includes/db_connect.php'; // Assume que define $mysqli e faz session_start()
require_once '../includes/check_auth.php'; // Garante que $_SESSION['user_id'] existe

// Resposta Padrão
$response = ['success' => false, 'message' => 'Ação inválida ou não especificada.'];

// Verifica se o usuário está autenticado (check_auth.php deve garantir isso)
if (!isset($_SESSION['user_id'])) {
    // check_auth.php já deve ter redirecionado ou encerrado, mas por segurança:
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}
$userId = $_SESSION['user_id']; // Pega o ID do usuário logado

// --- Processamento da Requisição ---
$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['action'])) {
    $action = $input['action'];
    error_log("[API User $userId] Ação Recebida: " . $action); // Log da ação recebida

    // Usar try...catch geral para pegar erros inesperados
    try {
        switch ($action) {

            // --- Carregar Notas ---
            case 'load':
                $sql = "SELECT id, title, content, category_id FROM notes WHERE user_id = ? ORDER BY updated_at DESC";
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("i", $userId);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $notes = [];
                         while($row = $result->fetch_assoc()) {
                            $row['id'] = (string)$row['id'];
                            $row['category_id'] = (string)$row['category_id'];
                            $notes[] = $row;
                         }
                        $response = ['success' => true, 'notes' => $notes];
                        error_log("[API User $userId] Load Notes: Sucesso, " . count($notes) . " notas encontradas.");
                    } else {
                        $response['message'] = "Erro ao buscar notas: " . $stmt->error;
                        error_log("[API User $userId] Erro execute load notes: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                     $response['message'] = "Erro ao preparar consulta (load): " . $mysqli->error;
                     error_log("[API User $userId] Erro prepare load notes: " . $mysqli->error);
                }
                break;

            // --- Salvar Nota ---
            case 'save':
                if (isset($input['note']) && is_array($input['note'])) {
                    $noteData = $input['note'];
                    $noteId = isset($noteData['id']) && $noteData['id'] !== null && $noteData['id'] !== 'null' ? (int)$noteData['id'] : null;
                    $title = isset($noteData['title']) ? trim($noteData['title']) : 'Nota sem título';
                    $content = $noteData['content'] ?? '';
                    $categoryId = isset($noteData['category_id']) ? (int)$noteData['category_id'] : null;

                    if (empty($title)) $title = "Nota sem título";

                    if ($categoryId === null || $categoryId <= 0) {
                         $response['message'] = "ID da categoria inválido ou não fornecido.";
                         error_log("[API User $userId] Erro save note: Category ID inválido ($categoryId).");
                         break;
                    }

                    $catCheckStmt = $mysqli->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
                    $catCheckStmt->bind_param("ii", $categoryId, $userId);
                    $catCheckStmt->execute();
                    $catCheckStmt->store_result();
                    if ($catCheckStmt->num_rows === 0) {
                        $response['message'] = "Categoria inválida ou não pertence a você.";
                        error_log("[API User $userId] Erro save note: Categoria $categoryId não encontrada para usuário.");
                        $catCheckStmt->close();
                        break;
                    }
                    $catCheckStmt->close();


                    if ($noteId) { // UPDATE
                        error_log("[API User $userId] Tentando UPDATE Nota ID: $noteId, Cat ID: $categoryId");
                        $sql = "UPDATE notes SET title = ?, content = ?, category_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
                         if ($stmt = $mysqli->prepare($sql)) {
                            $stmt->bind_param("ssiii", $title, $content, $categoryId, $noteId, $userId);
                            if ($stmt->execute()) {
                                 $affectedRows = $stmt->affected_rows;
                                 $stmt->close();
                                 if ($affectedRows >= 0) {
                                     $response = ['success' => true, 'note_id' => (string)$noteId, 'message' => ($affectedRows > 0 ? 'Nota atualizada.' : 'Nenhuma alteração.')];
                                     error_log("[API User $userId] Update Note $noteId: Sucesso ($affectedRows afetadas).");
                                 }
                            } else {
                                $errorMsg = $stmt->error;
                                $stmt->close();
                                $response['message'] = "Erro ao atualizar nota: " . $errorMsg;
                                error_log("[API User $userId] Erro execute update note $noteId: " . $errorMsg);
                            }
                        } else {
                            $response['message'] = "Erro ao preparar update: " . $mysqli->error;
                            error_log("[API User $userId] Erro prepare update note $noteId: " . $mysqli->error);
                        }

                    } else { // INSERT
                        error_log("[API User $userId] Tentando INSERT Nova Nota, Cat ID: $categoryId");
                        $sql = "INSERT INTO notes (user_id, category_id, title, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
                         if ($stmt = $mysqli->prepare($sql)) {
                            $stmt->bind_param("iiss", $userId, $categoryId, $title, $content);
                            if ($stmt->execute()) {
                                $newNoteId = $mysqli->insert_id;
                                $stmt->close();
                                $response = ['success' => true, 'message' => 'Nota criada.', 'note_id' => (string)$newNoteId];
                                error_log("[API User $userId] Insert Note: Sucesso, Novo ID: $newNoteId");
                            } else {
                                $errorMsg = $stmt->error;
                                $stmt->close();
                                $response['message'] = "Erro ao criar nota: " . $errorMsg;
                                error_log("[API User $userId] Erro execute insert note: " . $errorMsg);
                            }
                        } else {
                            $response['message'] = "Erro ao preparar insert: " . $mysqli->error;
                            error_log("[API User $userId] Erro prepare insert note: " . $mysqli->error);
                        }
                    }
                } else {
                    $response['message'] = "Dados da nota incompletos ou inválidos.";
                    error_log("[API User $userId] Erro save note: Dados incompletos - " . json_encode($input['note'] ?? null));
                }
                break;

            // --- Deletar Nota ---
            case 'delete':
                 if (isset($input['note_id'])) {
                    $noteIdToDelete = $input['note_id'];
                     if(is_numeric($noteIdToDelete)) {
                         error_log("[API User $userId] Tentando DELETE Nota ID: $noteIdToDelete");
                         $sql = "DELETE FROM notes WHERE id = ? AND user_id = ?";
                         if ($stmt = $mysqli->prepare($sql)) {
                             $stmt->bind_param("ii", $noteIdToDelete, $userId);
                             if ($stmt->execute()) {
                                 $affectedRows = $stmt->affected_rows;
                                 $stmt->close();
                                 if ($affectedRows > 0) {
                                     $response = ['success' => true, 'message' => 'Nota excluída.'];
                                     error_log("[API User $userId] Delete Note $noteIdToDelete: Sucesso.");
                                 } else {
                                     $response = ['success' => true, 'message' => 'Nota não encontrada ou já excluída.'];
                                     error_log("[API User $userId] Delete Note $noteIdToDelete: Não encontrada ou 0 linhas afetadas.");
                                 }
                             } else {
                                  $errorMsg = $stmt->error;
                                  $stmt->close();
                                  $response['message'] = "Erro ao excluir nota: " . $errorMsg;
                                  error_log("[API User $userId] Erro execute delete note $noteIdToDelete: " . $errorMsg);
                                  if (strpos($errorMsg, 'foreign key constraint fails') !== false) {
                                     $response['message'] = 'Não é possível excluir: verifique dependências (notas?).';
                                  }
                             }
                         } else {
                             $response['message'] = "Erro ao preparar delete: " . $mysqli->error;
                             error_log("[API User $userId] Erro prepare delete note $noteIdToDelete: " . $mysqli->error);
                         }
                     } else {
                          $response['message'] = "ID de nota inválido para exclusão.";
                          error_log("[API User $userId] Erro delete note: ID inválido ($noteIdToDelete).");
                     }
                } else {
                    $response['message'] = "ID da nota não fornecido para exclusão.";
                    error_log("[API User $userId] Erro delete note: ID não fornecido.");
                }
                break;

            // --- Carregar Categorias (AJUSTADO PARA USAR 'order' consistentemente) ---
            case 'load_categories':
                error_log("[API User $userId] Tentando LOAD CATEGORIES");
                // Usar o nome da coluna de ordem que você tem no banco, ex: 'display_order' ou 'category_order'
                $sql = "SELECT id, name, display_order AS `order` FROM categories WHERE user_id = ? ORDER BY `order` ASC, name ASC"; // Renomeia para 'order' no resultado
                if ($stmt = $mysqli->prepare($sql)) {
                    $stmt->bind_param("i", $userId);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $categories = [];
                        while($row = $result->fetch_assoc()) {
                            $row['id'] = (string)$row['id'];
                            // A coluna já foi renomeada para 'order' no SQL
                            $row['order'] = isset($row['order']) ? (int)$row['order'] : 0; // Garante que é int
                            $categories[] = $row;
                        }
                        $stmt->close();
                        $response = ['success' => true, 'categories' => $categories];
                        error_log("[API User $userId] Load Categories: Sucesso, " . count($categories) . " categorias encontradas.");
                    } else {
                        $errorMsg = $stmt->error;
                        $stmt->close();
                        $response['message'] = "Erro ao buscar categorias: " . $errorMsg;
                        error_log("[API User $userId] Erro execute load categories: " . $errorMsg);
                    }
                } else {
                    $response['message'] = "Erro ao preparar consulta (load_categories): " . $mysqli->error;
                    error_log("[API User $userId] Erro prepare load categories: " . $mysqli->error);
                }
                break;

            // --- Criar Categoria (AJUSTADO para receber 'order') ---
            case 'create_category':
                if (isset($input['name']) && !empty(trim($input['name']))) {
                    $category_name = trim($input['name']);
                    // Pega a ordem do JavaScript. Se não vier, pode definir um padrão ou calcular.
                    $order = isset($input['order']) && is_numeric($input['order']) ? (int)$input['order'] : 0;
                    error_log("[API User $userId] Tentando CREATE CATEGORY: Name='$category_name', Order=$order");

                    if (mb_strlen($category_name) > 150) {
                         $response['message'] = 'Nome da categoria muito longo (máx 150).';
                         error_log("[API User $userId] Erro create category: Nome muito longo.");
                         break;
                    }

                    $checkStmt = $mysqli->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                    $checkStmt->bind_param("is", $userId, $category_name);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $exists = $checkResult->num_rows > 0;
                    $checkStmt->close();

                    if ($exists) {
                        $response['message'] = 'Você já possui uma categoria com este nome.';
                        error_log("[API User $userId] Erro create category: Nome duplicado.");
                        break;
                    }

                    // Usar o nome da coluna de ordem do seu banco, ex: 'display_order'
                    $insertStmt = $mysqli->prepare("INSERT INTO categories (user_id, name, display_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                    $insertStmt->bind_param("isi", $userId, $category_name, $order);

                    if ($insertStmt->execute()) {
                        $new_category_id = $mysqli->insert_id;
                        $insertStmt->close();
                        $response = [
                            'success' => true,
                            'message' => 'Categoria criada!',
                            'category' => [
                                'id' => (string)$new_category_id,
                                'name' => $category_name,
                                'order' => $order // Retorna a ordem usada/definida
                            ]
                        ];
                        error_log("[API User $userId] Create Category: Sucesso, Novo ID: $new_category_id, Order: $order");
                    } else {
                        $errorMsg = $insertStmt->error;
                        $insertStmt->close();
                        $response['message'] = "Erro ao inserir categoria: " . $errorMsg;
                        error_log("[API User $userId] Erro execute insert category: " . $errorMsg);
                    }
                } else {
                    $response['message'] = 'Nome da categoria inválido ou não fornecido.';
                    error_log("[API User $userId] Erro create category: Nome inválido/ausente.");
                }
                break;

            // --- Renomear Categoria ---
            case 'update_category':
                if (isset($input['category_id'], $input['name']) && !empty(trim($input['name']))) {
                    $categoryId = (int)$input['category_id'];
                    $newName = trim($input['name']);
                    error_log("[API User $userId] Tentando UPDATE CATEGORY: ID=$categoryId, NewName='$newName'");

                    if (mb_strlen($newName) > 150) {
                         $response['message'] = 'Nome muito longo (máx 150).'; break;
                    }

                     $checkStmt = $mysqli->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ?");
                     $checkStmt->bind_param("isi", $userId, $newName, $categoryId);
                     $checkStmt->execute();
                     $checkResult = $checkStmt->get_result();
                     $exists = $checkResult->num_rows > 0;
                     $checkStmt->close();
                     if ($exists) { $response['message'] = 'Já existe outra categoria com este nome.'; break; }

                    // Usar o nome da coluna de ordem do seu banco, ex: 'display_order'
                    $updateStmt = $mysqli->prepare("UPDATE categories SET name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $updateStmt->bind_param("sii", $newName, $categoryId, $userId);

                    if ($updateStmt->execute()) {
                        $affectedRows = $updateStmt->affected_rows;
                        $updateStmt->close();
                        if ($affectedRows > 0) {
                             $response = ['success' => true, 'message' => 'Categoria renomeada!'];
                             error_log("[API User $userId] Update Category $categoryId: Sucesso.");
                        } else {
                             $findStmt = $mysqli->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
                             $findStmt->bind_param("ii", $categoryId, $userId); $findStmt->execute(); $findResult = $findStmt->get_result(); $found = $findResult->num_rows > 0; $findStmt->close();
                             if ($found) $response = ['success' => true, 'message' => 'Nome não alterado.'];
                             else $response['message'] = 'Categoria não encontrada ou pertence a outro usuário.';
                             error_log("[API User $userId] Update Category $categoryId: Nenhuma linha afetada (encontrada: $found).");
                        }
                    } else {
                        $errorMsg = $updateStmt->error; $updateStmt->close();
                        $response['message'] = "Erro ao renomear categoria: " . $errorMsg;
                        error_log("[API User $userId] Erro execute update category $categoryId: " . $errorMsg);
                    }
                } else {
                    $response['message'] = 'Dados inválidos para renomear categoria.';
                    error_log("[API User $userId] Erro update category: Dados inválidos - " . json_encode($input));
                }
                break;

            // --- Excluir Categoria ---
            case 'delete_category':
                if (isset($input['category_id'])) {
                    $categoryIdToDelete = (int)$input['category_id'];
                    $deleteAssociatedNotes = isset($input['delete_notes']) && $input['delete_notes'] === true;
                    error_log("[API User $userId] Tentando DELETE CATEGORY: ID=$categoryIdToDelete, DeleteNotes=$deleteAssociatedNotes");

                    $mysqli->begin_transaction();
                    try {
                        $catCheckStmt = $mysqli->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
                        $catCheckStmt->bind_param("ii", $categoryIdToDelete, $userId);
                        $catCheckStmt->execute();
                        $catCheckResult = $catCheckStmt->get_result();
                        $categoryExists = $catCheckResult->num_rows > 0;
                        $catCheckStmt->close();

                        if (!$categoryExists) {
                             throw new Exception('Categoria não encontrada ou não pertence a você.');
                        }

                        if ($deleteAssociatedNotes) {
                            error_log("[API User $userId] Deletando notas da categoria $categoryIdToDelete...");
                            $deleteNotesStmt = $mysqli->prepare("DELETE FROM notes WHERE category_id = ? AND user_id = ?");
                            $deleteNotesStmt->bind_param("ii", $categoryIdToDelete, $userId);
                            if (!$deleteNotesStmt->execute()) {
                                throw new Exception("Erro ao excluir notas associadas: " . $deleteNotesStmt->error);
                            }
                            $notesDeletedCount = $deleteNotesStmt->affected_rows;
                            $deleteNotesStmt->close();
                            error_log("[API User $userId] Notas excluídas: $notesDeletedCount");
                        } else {
                            $noteCheckStmt = $mysqli->prepare("SELECT COUNT(*) as note_count FROM notes WHERE category_id = ? AND user_id = ?");
                            $noteCheckStmt->bind_param("ii", $categoryIdToDelete, $userId);
                            $noteCheckStmt->execute();
                            $noteResult = $noteCheckStmt->get_result();
                            $noteCountRow = $noteResult->fetch_assoc();
                            $noteCheckStmt->close();

                            if ($noteCountRow && $noteCountRow['note_count'] > 0) {
                                throw new Exception('Exclua ou mova as notas desta categoria primeiro.');
                            }
                             error_log("[API User $userId] Categoria $categoryIdToDelete está vazia, prosseguindo com delete.");
                        }

                        error_log("[API User $userId] Deletando categoria $categoryIdToDelete...");
                        $deleteCatStmt = $mysqli->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
                        $deleteCatStmt->bind_param("ii", $categoryIdToDelete, $userId);
                        if (!$deleteCatStmt->execute()) {
                             throw new Exception("Erro ao excluir a categoria: " . $deleteCatStmt->error);
                        }
                        $catDeletedCount = $deleteCatStmt->affected_rows;
                        $deleteCatStmt->close();

                        if ($catDeletedCount > 0) {
                            $mysqli->commit();
                            $response = ['success' => true, 'message' => 'Categoria e/ou notas excluídas.'];
                            error_log("[API User $userId] Delete Category $categoryIdToDelete: Transação COMMITADA.");
                        } else {
                             throw new Exception('Categoria não encontrada no momento da exclusão final.');
                        }
                    } catch (Exception $e) {
                        $mysqli->rollback();
                        $response['message'] = $e->getMessage();
                        error_log("[API User $userId] Erro delete category $categoryIdToDelete: " . $e->getMessage() . ". ROLLBACK EXECUTADO.");
                    }
                } else {
                    $response['message'] = 'ID da categoria não fornecido.';
                    error_log("[API User $userId] Erro delete category: ID não fornecido.");
                }
                break;

            // --- NOVO CASE: Atualizar Ordem das Categorias ---
            case 'update_category_order':
                if (isset($input['category_orders']) && is_array($input['category_orders'])) {
                    $category_orders_data = $input['category_orders'];
                    error_log("[API User $userId] Tentando UPDATE CATEGORY ORDER. Dados: " . json_encode($category_orders_data));

                    $mysqli->begin_transaction();
                    $all_successful = true;
                    $updated_count = 0;

                    try {
                        // Usar o nome da coluna de ordem do seu banco, ex: 'display_order'
                        $stmt = $mysqli->prepare("UPDATE categories SET display_order = ? WHERE id = ? AND user_id = ?");
                        if (!$stmt) {
                            throw new Exception("Erro ao preparar statement para atualizar ordem: " . $mysqli->error);
                        }

                        foreach ($category_orders_data as $cat_order_item) {
                            if (isset($cat_order_item['id'], $cat_order_item['order']) &&
                                is_numeric($cat_order_item['id']) && is_numeric($cat_order_item['order'])) {
                                
                                $cat_id = (int)$cat_order_item['id'];
                                $order_value = (int)$cat_order_item['order'];

                                $stmt->bind_param("iii", $order_value, $cat_id, $userId);
                                if ($stmt->execute()) {
                                    if ($stmt->affected_rows > 0) {
                                        $updated_count++;
                                    }
                                    // Mesmo que affected_rows seja 0 (ordem já era a mesma), considera sucesso
                                } else {
                                    error_log("[API User $userId] Erro ao atualizar ordem para categoria ID {$cat_id} para ordem {$order_value}: " . $stmt->error);
                                    $all_successful = false; // Marcar que algo falhou
                                    // Não quebra o loop, tenta atualizar as outras
                                }
                            } else {
                                error_log("[API User $userId] Dados de ordem inválidos para um item: " . json_encode($cat_order_item));
                                $all_successful = false;
                            }
                        }
                        $stmt->close();

                        if ($all_successful) {
                            $mysqli->commit();
                            $response = ['success' => true, 'message' => "Ordem das categorias atualizada. ($updated_count categorias modificadas)"];
                            error_log("[API User $userId] Update Category Order: Sucesso, COMMITADO. $updated_count categorias modificadas.");
                        } else {
                            $mysqli->rollback();
                            $response = ['success' => false, 'message' => 'Falha ao atualizar a ordem de uma ou mais categorias. As alterações foram revertidas.'];
                            error_log("[API User $userId] Update Category Order: Falha em um ou mais updates. ROLLBACK EXECUTADO.");
                        }

                    } catch (Exception $e) {
                        $mysqli->rollback();
                        error_log("[API User $userId] Exceção ao atualizar ordem das categorias: " . $e->getMessage());
                        $response = ['success' => false, 'message' => 'Erro no servidor ao atualizar ordem das categorias: ' . $e->getMessage()];
                    }
                } else {
                    $response['message'] = 'Dados de ordem de categoria ausentes ou inválidos.';
                    error_log("[API User $userId] Erro update_category_order: Dados ausentes/inválidos.");
                }
                break;


            default:
                 $response['message'] = "Ação desconhecida: '{$action}'";
                 error_log("[API User $userId] Erro: Ação desconhecida recebida: " . $action);
                break;
        }
    } catch (Exception $e) {
        error_log("[API User $userId] Exceção não capturada: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
        $response['message'] = 'Erro interno do servidor.';
    }

} else {
    $response['message'] = 'Requisição inválida ou sem ação.';
    error_log("[API] Requisição inválida recebida (sem JSON ou ação). Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
}

if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
    $mysqli->close();
}

echo json_encode($response);
exit();
?>