<?php
/**
 * Support Tickets Module
 * Verwaltung von Support-Tickets durch Administratoren
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class SupportTicketsModule extends ModuleBase {
    
    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="alert alert-danger">' . $this->t('access_denied') . '</div>';
        }
        
        try {
            return $this->render('main', [
                'translations' => $this->tMultiple([
                    'module_title', 'tickets', 'new_tickets', 'open_tickets', 'closed_tickets',
                    'ticket_id', 'customer', 'subject', 'priority', 'status', 'created', 'updated',
                    'actions', 'view', 'edit', 'delete', 'reply', 'close', 'reopen', 'change_priority',
                    'low', 'medium', 'high', 'urgent', 'open', 'in_progress', 'waiting_customer', 
                    'waiting_admin', 'resolved', 'closed', 'message', 'reply_message', 'send_reply',
                    'confirm_delete', 'confirm_close', 'confirm_reopen', 'loading', 'no_tickets',
                    'filter_by_status', 'filter_by_priority', 'search_tickets', 'all_tickets',
                    'customer_name', 'email', 'phone', 'company', 'ticket_number', 'category',
                    'assigned_to', 'department', 'source', 'estimated_resolution_time',
                    'actual_resolution_time', 'customer_satisfaction', 'internal_notes', 'tags',
                    'due_date', 'resolved_at', 'closed_at', 'last_reply', 'replies_count',
                    'attachments', 'add_attachment', 'remove_attachment', 'file_size_limit',
                    'allowed_file_types', 'upload_progress', 'upload_success', 'upload_error',
                    'save_changes', 'cancel', 'back', 'refresh', 'export', 'import', 'bulk_actions',
                    'select_all', 'deselect_all', 'bulk_close', 'bulk_delete', 'bulk_assign',
                    'bulk_change_priority', 'bulk_change_status', 'bulk_export', 'bulk_import',
                    'statistics', 'tickets_by_status', 'tickets_by_priority', 'tickets_by_department',
                    'average_resolution_time', 'customer_satisfaction_avg', 'tickets_per_day',
                    'tickets_per_week', 'tickets_per_month', 'response_time_avg', 'escalation_rate',
                    'auto_assignment', 'auto_escalation', 'auto_notification', 'auto_reminder',
                    'settings', 'general_settings', 'notification_settings', 'escalation_settings',
                    'auto_assignment_settings', 'template_settings', 'email_templates',
                    'sms_templates', 'webhook_settings', 'api_settings', 'integration_settings',
                    'custom_fields', 'add_custom_field', 'edit_custom_field', 'delete_custom_field',
                    'field_name', 'field_type', 'field_required', 'field_options', 'field_default',
                    'field_validation', 'field_help_text', 'field_order', 'field_active',
                    'reports', 'generate_report', 'report_type', 'report_period', 'report_format',
                    'report_columns', 'report_filters', 'report_schedule', 'report_email',
                    'report_recipients', 'report_frequency', 'daily', 'weekly', 'monthly',
                    'quarterly', 'yearly', 'custom', 'report_name', 'report_description',
                    'save_report', 'load_report', 'delete_report', 'share_report', 'export_report',
                    'print_report', 'email_report', 'schedule_report', 'unschedule_report',
                    'report_history', 'report_logs', 'report_errors', 'report_success',
                    'help', 'documentation', 'faq', 'contact_support', 'feedback', 'bug_report',
                    'feature_request', 'improvement_suggestion', 'rate_module', 'module_reviews',
                    'module_rating', 'module_comments', 'module_suggestions', 'module_bugs',
                    'module_features', 'module_improvements', 'module_help', 'module_docs',
                    'module_tutorial', 'module_video', 'module_screenshots', 'module_examples',
                    'module_templates', 'module_themes', 'module_plugins', 'module_extensions',
                    'module_updates', 'module_version', 'module_changelog', 'module_license',
                    'module_author', 'module_website', 'module_support', 'module_donation',
                    'module_affiliate', 'module_partner', 'module_reseller', 'module_developer',
                    'module_contributor', 'module_translator', 'module_designer', 'module_tester',
                    'module_reviewer', 'module_moderator', 'module_admin', 'module_super_admin'
                ])
            ]);
        } catch (Exception $e) {
            error_log("SupportTicketsModule getContent error: " . $e->getMessage());
            return '<div class="alert alert-danger">' . $this->t('error_loading_module') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'get_tickets':
                return $this->getTickets($data);
                
            case 'get_ticket':
                return $this->getTicket($data);
                
            case 'create_ticket':
                return $this->createTicket($data);
                
            case 'update_ticket':
                return $this->updateTicket($data);
                
            case 'delete_ticket':
                return $this->deleteTicket($data);
                
            case 'reply_ticket':
                return $this->replyTicket($data);
                
            case 'close_ticket':
                return $this->closeTicket($data);
                
            case 'reopen_ticket':
                return $this->reopenTicket($data);
                
            case 'change_priority':
                return $this->changePriority($data);
                
            case 'change_status':
                return $this->changeStatus($data);
                
            case 'assign_ticket':
                return $this->assignTicket($data);
                
            case 'get_ticket_replies':
                return $this->getTicketReplies($data);
                
            case 'add_internal_note':
                return $this->addInternalNote($data);
                
            case 'get_statistics':
                return $this->getStatistics();
                
            case 'bulk_action':
                return $this->bulkAction($data);
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function createTicket($data) {
        // Diese Methode wird für Admin-Erstellung von Tickets verwendet
        try {
            $customerId = $data['customer_id'] ?? 0;
            $subject = trim($data['subject'] ?? '');
            $message = trim($data['message'] ?? '');
            $priority = $data['priority'] ?? 'medium';
            $category = $data['category'] ?? '';
            $department = $data['department'] ?? '';
            
            if (!$customerId || !$subject || !$message) {
                return $this->error('Customer ID, subject and message required');
            }
            
            $db = Database::getInstance();
            
            // Eindeutige Ticket-Nummer generieren
            do {
                $ticketNumber = 'TIC-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Prüfen ob Ticket-Nummer bereits existiert
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE ticket_number = ?");
                $checkStmt->execute([$ticketNumber]);
            } while ($checkStmt->fetchColumn() > 0);
            
            // E-Mail-Adresse des Kunden holen
            $emailStmt = $db->prepare("SELECT email FROM customers WHERE id = ?");
            $emailStmt->execute([$customerId]);
            $customerEmail = $emailStmt->fetchColumn() ?: '';
            
            $stmt = $db->prepare("
                INSERT INTO support_tickets (ticket_number, customer_id, email, subject, priority, message, category, department, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            if ($stmt->execute([$ticketNumber, $customerId, $customerEmail, $subject, $priority, $message, $category, $department])) {
                $ticketId = $db->lastInsertId();
                $this->log("Ticket $ticketId created by admin {$this->user_id}", 'INFO');
                return $this->success(['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber]);
            } else {
                return $this->error('Failed to create ticket');
            }
            
        } catch (Exception $e) {
            $this->log('Error creating ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function assignTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            $assignedTo = $data['assigned_to'] ?? '';
            
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET assigned_to = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$assignedTo, $ticketId]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId assigned to $assignedTo by user {$this->user_id}", 'INFO');
                return $this->success('Ticket assigned successfully');
            } else {
                return $this->error('Ticket not found');
            }
            
        } catch (Exception $e) {
            $this->log('Error assigning ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_assigning_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function getTicketReplies($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            $sql = "
                SELECT tr.*, 
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                           WHEN tr.admin_id IS NOT NULL THEN 'Admin'
                           ELSE 'System'
                       END as author_name,
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN c.email
                           ELSE 'admin@system.com'
                       END as author_email
                FROM ticket_replies tr
                LEFT JOIN customers c ON tr.customer_id = c.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.created_at ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$ticketId]);
            $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->success($replies);
            
        } catch (Exception $e) {
            $this->log('Error getting ticket replies: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_replies') . ': ' . $e->getMessage());
        }
    }
    
    private function addInternalNote($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            $note = trim($data['note'] ?? '');
            
            if (!$ticketId || !$note) {
                return $this->error('Ticket ID and note required');
            }
            
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                INSERT INTO ticket_replies (ticket_id, admin_id, message, is_internal, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$ticketId, $this->user_id, $note]);
            
            $this->log("Internal note added to ticket $ticketId by user {$this->user_id}", 'INFO');
            return $this->success('Internal note added successfully');
            
        } catch (Exception $e) {
            $this->log('Error adding internal note: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_adding_note') . ': ' . $e->getMessage());
        }
    }
    
    private function getTickets($data) {
        try {
            $db = Database::getInstance();
            
            $page = (int)($data['page'] ?? 1);
            $limit = (int)($data['limit'] ?? 20);
            $offset = ($page - 1) * $limit;
            
            $status = $data['status'] ?? '';
            $priority = $data['priority'] ?? '';
            $search = $data['search'] ?? '';
            
            $whereConditions = [];
            $params = [];
            
            if ($status) {
                $whereConditions[] = "st.status = ?";
                $params[] = $status;
            }
            
            if ($priority) {
                $whereConditions[] = "st.priority = ?";
                $params[] = $priority;
            }
            
            if ($search) {
                $whereConditions[] = "(st.subject LIKE ? OR st.message LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }
            
            // Tickets mit Kundeninformationen laden
            $sql = "
                SELECT st.*, 
                       c.first_name, c.last_name, c.email as customer_email, c.phone, c.company,
                       (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = st.id) as replies_count,
                       (SELECT MAX(tr.created_at) FROM ticket_replies tr WHERE tr.ticket_id = st.id) as last_reply
                FROM support_tickets st
                LEFT JOIN customers c ON st.customer_id = c.id
                $whereClause
                ORDER BY st.created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Gesamtanzahl für Pagination
            $countSql = "
                SELECT COUNT(*) 
                FROM support_tickets st
                LEFT JOIN customers c ON st.customer_id = c.id
                $whereClause
            ";
            
            $countParams = $params; // Alle Parameter verwenden, da LIMIT/OFFSET nicht mehr in $params sind
            $stmt = $db->prepare($countSql);
            $stmt->execute($countParams);
            $totalCount = $stmt->fetchColumn();
            
            return $this->success([
                'tickets' => $tickets,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            $this->log('Error getting tickets: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_tickets') . ': ' . $e->getMessage());
        }
    }
    
    private function getTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            // Ticket mit Kundeninformationen laden
            $sql = "
                SELECT st.*, 
                       c.first_name, c.last_name, c.email as customer_email, c.phone, c.company
                FROM support_tickets st
                LEFT JOIN customers c ON st.customer_id = c.id
                WHERE st.id = ?
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                return $this->error('Ticket not found');
            }
            
            // Ticket-Replies laden
            $repliesSql = "
                SELECT tr.*, 
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                           WHEN tr.admin_id IS NOT NULL THEN 'Admin'
                           ELSE 'System'
                       END as author_name,
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN c.email
                           ELSE 'admin@system.com'
                       END as author_email
                FROM ticket_replies tr
                LEFT JOIN customers c ON tr.customer_id = c.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.created_at ASC
            ";
            
            $stmt = $db->prepare($repliesSql);
            $stmt->execute([$ticketId]);
            $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $ticket['replies'] = $replies;
            
            return $this->success($ticket);
            
        } catch (Exception $e) {
            $this->log('Error getting ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function updateTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            $updates = [];
            $params = [];
            
            // Felder die aktualisiert werden können
            $allowedFields = ['priority', 'status', 'assigned_to', 'department', 'category', 'internal_notes', 'tags', 'due_date'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return $this->error('No fields to update');
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $ticketId;
            
            $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId updated by user {$this->user_id}", 'INFO');
                return $this->success('Ticket updated successfully');
            } else {
                return $this->error('No changes made');
            }
            
        } catch (Exception $e) {
            $this->log('Error updating ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_updating_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            // Transaction starten
            $db->beginTransaction();
            
            try {
                // Ticket-Replies löschen
                $stmt = $db->prepare("DELETE FROM ticket_replies WHERE ticket_id = ?");
                $stmt->execute([$ticketId]);
                
                // Ticket löschen
                $stmt = $db->prepare("DELETE FROM support_tickets WHERE id = ?");
                $stmt->execute([$ticketId]);
                
                if ($stmt->rowCount() > 0) {
                    $db->commit();
                    $this->log("Ticket $ticketId deleted by user {$this->user_id}", 'INFO');
                    return $this->success('Ticket deleted successfully');
                } else {
                    $db->rollBack();
                    return $this->error('Ticket not found');
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->log('Error deleting ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function replyTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            $message = trim($data['message'] ?? '');
            $isInternal = $data['is_internal'] ?? false;
            
            if (!$ticketId || !$message) {
                return $this->error('Ticket ID and message required');
            }
            
            $db = Database::getInstance();
            
            // Transaction starten
            $db->beginTransaction();
            
            try {
                // Reply hinzufügen
                $stmt = $db->prepare("
                    INSERT INTO ticket_replies (ticket_id, admin_id, message, is_internal, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$ticketId, $this->user_id, $message, $isInternal ? 1 : 0]);
                
                // Ticket-Status aktualisieren (wenn nicht intern)
                if (!$isInternal) {
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET status = 'waiting_customer', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$ticketId]);
                }
                
                $db->commit();
                
                $this->log("Reply added to ticket $ticketId by user {$this->user_id}", 'INFO');
                return $this->success('Reply added successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->log('Error adding reply: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_adding_reply') . ': ' . $e->getMessage());
        }
    }
    
    private function closeTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET status = 'closed', closed_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId closed by user {$this->user_id}", 'INFO');
                return $this->success('Ticket closed successfully');
            } else {
                return $this->error('Ticket not found');
            }
            
        } catch (Exception $e) {
            $this->log('Error closing ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_closing_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function reopenTicket($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            if (!$ticketId) {
                return $this->error('Ticket ID required');
            }
            
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET status = 'open', closed_at = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId reopened by user {$this->user_id}", 'INFO');
                return $this->success('Ticket reopened successfully');
            } else {
                return $this->error('Ticket not found');
            }
            
        } catch (Exception $e) {
            $this->log('Error reopening ticket: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_reopening_ticket') . ': ' . $e->getMessage());
        }
    }
    
    private function changePriority($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            $priority = $data['priority'] ?? '';
            
            if (!$ticketId || !$priority) {
                return $this->error('Ticket ID and priority required');
            }
            
            $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
            if (!in_array($priority, $allowedPriorities)) {
                return $this->error('Invalid priority');
            }
            
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET priority = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$priority, $ticketId]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId priority changed to $priority by user {$this->user_id}", 'INFO');
                return $this->success('Priority changed successfully');
            } else {
                return $this->error('Ticket not found');
            }
            
        } catch (Exception $e) {
            $this->log('Error changing priority: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_changing_priority') . ': ' . $e->getMessage());
        }
    }
    
    private function changeStatus($data) {
        try {
            $ticketId = $data['ticket_id'] ?? 0;
            $status = $data['status'] ?? '';
            
            if (!$ticketId || !$status) {
                return $this->error('Ticket ID and status required');
            }
            
            $allowedStatuses = ['open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed'];
            if (!in_array($status, $allowedStatuses)) {
                return $this->error('Invalid status');
            }
            
            $db = Database::getInstance();
            
            $updates = ["status = ?", "updated_at = NOW()"];
            $params = [$status, $ticketId];
            
            // Spezielle Behandlung für bestimmte Status
            if ($status === 'closed') {
                $updates[] = "closed_at = NOW()";
            } elseif ($status === 'resolved') {
                $updates[] = "resolved_at = NOW()";
            }
            
            $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Ticket $ticketId status changed to $status by user {$this->user_id}", 'INFO');
                return $this->success('Status changed successfully');
            } else {
                return $this->error('Ticket not found');
            }
            
        } catch (Exception $e) {
            $this->log('Error changing status: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_changing_status') . ': ' . $e->getMessage());
        }
    }
    
    private function getStatistics() {
        try {
            $db = Database::getInstance();
            
            $stats = [];
            
            // Tickets nach Status
            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count 
                FROM support_tickets 
                GROUP BY status
            ");
            $stmt->execute();
            $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tickets nach Priorität
            $stmt = $db->prepare("
                SELECT priority, COUNT(*) as count 
                FROM support_tickets 
                GROUP BY priority
            ");
            $stmt->execute();
            $stats['by_priority'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tickets nach Abteilung
            $stmt = $db->prepare("
                SELECT department, COUNT(*) as count 
                FROM support_tickets 
                GROUP BY department
            ");
            $stmt->execute();
            $stats['by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Gesamtanzahl
            $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets");
            $stmt->execute();
            $stats['total'] = $stmt->fetchColumn();
            
            // Offene Tickets
            $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'in_progress', 'waiting_admin')");
            $stmt->execute();
            $stats['open'] = $stmt->fetchColumn();
            
            // Geschlossene Tickets (letzte 30 Tage)
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM support_tickets 
                WHERE status = 'closed' AND closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $stats['closed_30_days'] = $stmt->fetchColumn();
            
            return $this->success($stats);
            
        } catch (Exception $e) {
            $this->log('Error getting statistics: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_statistics') . ': ' . $e->getMessage());
        }
    }
    
    private function bulkAction($data) {
        try {
            $ticketIds = $data['ticket_ids'] ?? [];
            $action = $data['bulk_action'] ?? '';
            
            if (empty($ticketIds) || !$action) {
                return $this->error('Ticket IDs and action required');
            }
            
            $db = Database::getInstance();
            $db->beginTransaction();
            
            try {
                $updatedCount = 0;
                
                switch ($action) {
                    case 'close':
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET status = 'closed', closed_at = NOW(), updated_at = NOW() 
                            WHERE id IN (" . str_repeat('?,', count($ticketIds) - 1) . "?)
                        ");
                        $stmt->execute($ticketIds);
                        $updatedCount = $stmt->rowCount();
                        break;
                        
                    case 'delete':
                        // Erst Replies löschen
                        $stmt = $db->prepare("
                            DELETE FROM ticket_replies 
                            WHERE ticket_id IN (" . str_repeat('?,', count($ticketIds) - 1) . "?)
                        ");
                        $stmt->execute($ticketIds);
                        
                        // Dann Tickets löschen
                        $stmt = $db->prepare("
                            DELETE FROM support_tickets 
                            WHERE id IN (" . str_repeat('?,', count($ticketIds) - 1) . "?)
                        ");
                        $stmt->execute($ticketIds);
                        $updatedCount = $stmt->rowCount();
                        break;
                        
                    case 'change_priority':
                        $priority = $data['priority'] ?? '';
                        if (!$priority) {
                            throw new Exception('Priority required for bulk priority change');
                        }
                        
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET priority = ?, updated_at = NOW() 
                            WHERE id IN (" . str_repeat('?,', count($ticketIds) - 1) . "?)
                        ");
                        $params = array_merge([$priority], $ticketIds);
                        $stmt->execute($params);
                        $updatedCount = $stmt->rowCount();
                        break;
                        
                    case 'change_status':
                        $status = $data['status'] ?? '';
                        if (!$status) {
                            throw new Exception('Status required for bulk status change');
                        }
                        
                        $updates = ["status = ?", "updated_at = NOW()"];
                        $params = [$status];
                        
                        if ($status === 'closed') {
                            $updates[] = "closed_at = NOW()";
                        } elseif ($status === 'resolved') {
                            $updates[] = "resolved_at = NOW()";
                        }
                        
                        $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE id IN (" . str_repeat('?,', count($ticketIds) - 1) . "?)";
                        $params = array_merge($params, $ticketIds);
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        $updatedCount = $stmt->rowCount();
                        break;
                        
                    default:
                        throw new Exception('Invalid bulk action');
                }
                
                $db->commit();
                
                $this->log("Bulk action '$action' performed on " . count($ticketIds) . " tickets by user {$this->user_id}", 'INFO');
                return $this->success("Bulk action completed. $updatedCount tickets updated.");
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->log('Error performing bulk action: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_bulk_action') . ': ' . $e->getMessage());
        }
    }
    
    protected function t($key, $default = null) {
        return $this->language_manager->translate($this->module_key, $key, $default);
    }
    
    protected function tMultiple($keys) {
        return $this->language_manager->translateMultiple($this->module_key, $keys);
    }
    
    protected function render($template, $data = []) {
        $template_path = $this->module_config['path'] . '/templates/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template not found: $template_path");
        }
        
        extract($data);
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
    
    protected function success($data = null, $message = 'Operation successful') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    protected function error($message = 'Operation failed', $data = null) {
        return [
            'success' => false,
            'error' => $message,
            'data' => $data
        ];
    }
    
    protected function log($message, $level = 'INFO') {
        if (function_exists('logActivity')) {
            logActivity($this->module_key . ': ' . $message, $level);
        } else {
            error_log("SupportTicketsModule [$level]: $message");
        }
    }
}
