<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

// Domain-Registrierungen Verwaltung für Admins

// Alle Domain-Registrierungen laden
$domainRegistrations = [];
try {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT dr.*, c.email, CONCAT(c.first_name, ' ', c.last_name) as full_name 
        FROM domain_registrations dr
        LEFT JOIN customers c ON dr.user_id = c.id
        ORDER BY dr.created_at DESC
    ");
    $stmt->execute();
    $domainRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading domain registrations: " . $e->getMessage());
    $errorMessage = t('error_loading_registrations');
}

// Statistiken berechnen
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0
];

foreach ($domainRegistrations as $reg) {
    $stats['total']++;
    $stats[$reg['status']]++;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-globe"></i> <?= t('domain_registrations_management') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Statistiken -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-primary text-white">
                                <div class="card-body">
                                    <h4 class="card-title"><?= $stats['total'] ?></h4>
                                    <p class="card-text"><?= t('total_registrations') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-warning text-white">
                                <div class="card-body">
                                    <h4 class="card-title"><?= $stats['pending'] ?></h4>
                                    <p class="card-text"><?= t('pending_approval') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-success text-white">
                                <div class="card-body">
                                    <h4 class="card-title"><?= $stats['approved'] ?></h4>
                                    <p class="card-text"><?= t('approved') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-danger text-white">
                                <div class="card-body">
                                    <h4 class="card-title"><?= $stats['rejected'] ?></h4>
                                    <p class="card-text"><?= t('rejected') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-secondary text-white">
                                <div class="card-body">
                                    <h4 class="card-title"><?= $stats['cancelled'] ?></h4>
                                    <p class="card-text"><?= t('cancelled') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter" onchange="filterRegistrations()">
                                <option value=""><?= t('all_statuses') ?></option>
                                <option value="pending"><?= t('pending_approval') ?></option>
                                <option value="approved"><?= t('approved') ?></option>
                                <option value="rejected"><?= t('rejected') ?></option>
                                <option value="cancelled"><?= t('cancelled') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="domainFilter" 
                                   placeholder="<?= t('search_domain') ?>" onkeyup="filterRegistrations()">
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="userFilter" 
                                   placeholder="<?= t('search_customer') ?>" onkeyup="filterRegistrations()">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary" onclick="refreshRegistrations()">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </button>
                        </div>
                    </div>

                    <!-- Domain-Registrierungen Tabelle -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="registrationsTable">
                            <thead>
                                <tr>
                                    <th><?= t('domain') ?></th>
                                    <th><?= t('customer') ?></th>
                                    <th><?= t('purpose') ?></th>
                                    <th><?= t('status') ?></th>
                                    <th><?= t('submitted') ?></th>
                                    <th><?= t('notes') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($domainRegistrations)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-6"></i>
                                            <p class="mt-2"><?= t('no_domain_registrations_found') ?></p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($domainRegistrations as $registration): ?>
                                        <tr class="registration-row" 
                                            data-status="<?= $registration['status'] ?>"
                                            data-domain="<?= strtolower($registration['domain']) ?>"
                                            data-user="<?= strtolower($registration['full_name'] ?? '') ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($registration['domain']) ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($registration['full_name'] ?? 'N/A') ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($registration['email'] ?? '') ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($registration['purpose']) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($registration['status']) {
                                                    case 'pending':
                                                        $statusClass = 'warning';
                                                        $statusText = t('pending_approval');
                                                        break;
                                                    case 'approved':
                                                        $statusClass = 'success';
                                                        $statusText = t('approved');
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'danger';
                                                        $statusText = t('rejected');
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'secondary';
                                                        $statusText = t('cancelled');
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <small><?= date('d.m.Y H:i', strtotime($registration['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($registration['notes']): ?>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="showNotes('<?= htmlspecialchars($registration['notes']) ?>')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($registration['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="approveRegistration(<?= $registration['id'] ?>)" 
                                                                title="<?= t('approve_registration') ?>">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" 
                                                                onclick="rejectRegistration(<?= $registration['id'] ?>)" 
                                                                title="<?= t('reject_registration') ?>">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($registration['status'], ['pending', 'approved', 'rejected'])): ?>
                                                        <button class="btn btn-outline-secondary" 
                                                                onclick="cancelRegistration(<?= $registration['id'] ?>)" 
                                                                title="<?= t('cancel_registration') ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editRegistration(<?= $registration['id'] ?>)" 
                                                            title="<?= t('edit_registration') ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('registration_notes') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="notesContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Registration Modal -->
<div class="modal fade" id="editRegistrationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('edit_registration') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRegistrationForm">
                    <input type="hidden" id="editRegistrationId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editDomain" class="form-label"><?= t('domain') ?></label>
                                <input type="text" class="form-control" id="editDomain" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editStatus" class="form-label"><?= t('status') ?></label>
                                <select class="form-select" id="editStatus">
                                    <option value="pending"><?= t('pending_approval') ?></option>
                                    <option value="approved"><?= t('approved') ?></option>
                                    <option value="rejected"><?= t('rejected') ?></option>
                                    <option value="cancelled"><?= t('cancelled') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPurpose" class="form-label"><?= t('purpose') ?></label>
                                <select class="form-select" id="editPurpose">
                                    <option value="business"><?= t('business_website') ?></option>
                                    <option value="personal"><?= t('personal_website') ?></option>
                                    <option value="blog"><?= t('blog') ?></option>
                                    <option value="ecommerce"><?= t('ecommerce') ?></option>
                                    <option value="portfolio"><?= t('portfolio') ?></option>
                                    <option value="other"><?= t('other') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label"><?= t('user_notes') ?></label>
                        <textarea class="form-control" id="editNotes" rows="3" readonly></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAdminNotes" class="form-label"><?= t('admin_notes') ?></label>
                        <textarea class="form-control" id="editAdminNotes" rows="3" 
                                  placeholder="<?= t('add_admin_notes') ?>"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="saveRegistrationChanges()">
                    <?= t('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Action Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionTitle"><?= t('confirm_action') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmActionButton"><?= t('confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript-Code wurde in assets/inc-js/domain-registrations.js ausgelagert -->
