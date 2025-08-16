<div class="support-tickets-module">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-headset"></i> <?= $translations['module_title'] ?? 'Support Tickets' ?></h2>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="loadStatistics()">
                <i class="bi bi-graph-up"></i> <?= $translations['statistics'] ?? 'Statistics' ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="showCreateTicketModal()">
                <i class="bi bi-plus-circle"></i> <?= $translations['new_tickets'] ?? 'New Ticket' ?>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label"><?= $translations['filter_by_status'] ?? 'Status' ?></label>
                    <select class="form-select" id="statusFilter" onchange="loadTickets()">
                        <option value=""><?= $translations['all_tickets'] ?? 'All Tickets' ?></option>
                        <option value="open"><?= $translations['open'] ?? 'Open' ?></option>
                        <option value="in_progress"><?= $translations['in_progress'] ?? 'In Progress' ?></option>
                        <option value="waiting_customer"><?= $translations['waiting_customer'] ?? 'Waiting Customer' ?></option>
                        <option value="waiting_admin"><?= $translations['waiting_admin'] ?? 'Waiting Admin' ?></option>
                        <option value="resolved"><?= $translations['resolved'] ?? 'Resolved' ?></option>
                        <option value="closed"><?= $translations['closed'] ?? 'Closed' ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priorityFilter" class="form-label"><?= $translations['filter_by_priority'] ?? 'Priority' ?></label>
                    <select class="form-select" id="priorityFilter" onchange="loadTickets()">
                        <option value=""><?= $translations['all_tickets'] ?? 'All Tickets' ?></option>
                        <option value="low"><?= $translations['low'] ?? 'Low' ?></option>
                        <option value="medium"><?= $translations['medium'] ?? 'Medium' ?></option>
                        <option value="high"><?= $translations['high'] ?? 'High' ?></option>
                        <option value="urgent"><?= $translations['urgent'] ?? 'Urgent' ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="searchInput" class="form-label"><?= $translations['search_tickets'] ?? 'Search' ?></label>
                    <input type="text" class="form-control" id="searchInput" placeholder="<?= $translations['search_tickets'] ?? 'Search tickets...' ?>" onkeyup="debounce(loadTickets, 500)()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="loadTickets()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="card mb-4" id="bulkActionsCard" style="display: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span id="selectedCount">0</span> <?= $translations['tickets'] ?? 'tickets' ?> <?= $translations['selected'] ?? 'selected' ?>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-warning" onclick="bulkAction('change_priority')">
                        <i class="bi bi-flag"></i> <?= $translations['bulk_change_priority'] ?? 'Change Priority' ?>
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="bulkAction('change_status')">
                        <i class="bi bi-arrow-repeat"></i> <?= $translations['bulk_change_status'] ?? 'Change Status' ?>
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="bulkAction('close')">
                        <i class="bi bi-check-circle"></i> <?= $translations['bulk_close'] ?? 'Close' ?>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="bulkAction('delete')">
                        <i class="bi bi-trash"></i> <?= $translations['bulk_delete'] ?? 'Delete' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th><?= $translations['ticket_id'] ?? 'ID' ?></th>
                            <th><?= $translations['customer'] ?? 'Customer' ?></th>
                            <th><?= $translations['subject'] ?? 'Subject' ?></th>
                            <th><?= $translations['priority'] ?? 'Priority' ?></th>
                            <th><?= $translations['status'] ?? 'Status' ?></th>
                            <th><?= $translations['created'] ?? 'Created' ?></th>
                            <th><?= $translations['updated'] ?? 'Updated' ?></th>
                            <th><?= $translations['actions'] ?? 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody id="ticketsTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden"><?= $translations['loading'] ?? 'Loading...' ?></span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Tickets pagination" id="paginationContainer">
                <!-- Pagination wird dynamisch geladen -->
            </nav>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="modal fade" id="createTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $translations['new_tickets'] ?? 'New Ticket' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createTicketForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customerSelect" class="form-label"><?= $translations['customer'] ?? 'Customer' ?></label>
                                <select class="form-select" id="customerSelect" name="customer_id" required>
                                    <option value=""><?= $translations['select_customer'] ?? 'Select Customer' ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prioritySelect" class="form-label"><?= $translations['priority'] ?? 'Priority' ?></label>
                                <select class="form-select" id="prioritySelect" name="priority">
                                    <option value="low"><?= $translations['low'] ?? 'Low' ?></option>
                                    <option value="medium" selected><?= $translations['medium'] ?? 'Medium' ?></option>
                                    <option value="high"><?= $translations['high'] ?? 'High' ?></option>
                                    <option value="urgent"><?= $translations['urgent'] ?? 'Urgent' ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoryInput" class="form-label"><?= $translations['category'] ?? 'Category' ?></label>
                                <input type="text" class="form-control" id="categoryInput" name="category">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="departmentInput" class="form-label"><?= $translations['department'] ?? 'Department' ?></label>
                                <input type="text" class="form-control" id="departmentInput" name="department">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subjectInput" class="form-label"><?= $translations['subject'] ?? 'Subject' ?></label>
                        <input type="text" class="form-control" id="subjectInput" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="messageInput" class="form-label"><?= $translations['message'] ?? 'Message' ?></label>
                        <textarea class="form-control" id="messageInput" name="message" rows="5" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $translations['cancel'] ?? 'Cancel' ?></button>
                <button type="button" class="btn btn-primary" onclick="createTicket()"><?= $translations['create'] ?? 'Create' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- View Ticket Modal -->
<div class="modal fade" id="viewTicketModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketModalTitle"><?= $translations['ticket_details'] ?? 'Ticket Details' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Ticket Information -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><?= $translations['ticket_information'] ?? 'Ticket Information' ?></h6>
                            </div>
                            <div class="card-body" id="ticketInfo">
                                <!-- Ticket details will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Replies -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><?= $translations['replies'] ?? 'Replies' ?></h6>
                            </div>
                            <div class="card-body" id="ticketReplies">
                                <!-- Replies will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <!-- Quick Actions -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><?= $translations['quick_actions'] ?? 'Quick Actions' ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="showReplyModal()">
                                        <i class="bi bi-reply"></i> <?= $translations['reply'] ?? 'Reply' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" onclick="showPriorityModal()">
                                        <i class="bi bi-flag"></i> <?= $translations['change_priority'] ?? 'Change Priority' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="showStatusModal()">
                                        <i class="bi bi-arrow-repeat"></i> <?= $translations['change_status'] ?? 'Change Status' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="closeTicket()">
                                        <i class="bi bi-check-circle"></i> <?= $translations['close'] ?? 'Close' ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteTicket()">
                                        <i class="bi bi-trash"></i> <?= $translations['delete'] ?? 'Delete' ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Internal Notes -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><?= $translations['internal_notes'] ?? 'Internal Notes' ?></h6>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" id="internalNote" rows="3" placeholder="<?= $translations['add_internal_note'] ?? 'Add internal note...' ?>"></textarea>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addInternalNote()">
                                    <?= $translations['add_note'] ?? 'Add Note' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $translations['reply_message'] ?? 'Reply to Ticket' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <div class="mb-3">
                        <label for="replyMessage" class="form-label"><?= $translations['message'] ?? 'Message' ?></label>
                        <textarea class="form-control" id="replyMessage" rows="5" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $translations['cancel'] ?? 'Cancel' ?></button>
                <button type="button" class="btn btn-primary" onclick="sendReply()"><?= $translations['send_reply'] ?? 'Send Reply' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Priority Modal -->
<div class="modal fade" id="priorityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $translations['change_priority'] ?? 'Change Priority' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newPriority" class="form-label"><?= $translations['priority'] ?? 'Priority' ?></label>
                    <select class="form-select" id="newPriority">
                        <option value="low"><?= $translations['low'] ?? 'Low' ?></option>
                        <option value="medium"><?= $translations['medium'] ?? 'Medium' ?></option>
                        <option value="high"><?= $translations['high'] ?? 'High' ?></option>
                        <option value="urgent"><?= $translations['urgent'] ?? 'Urgent' ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $translations['cancel'] ?? 'Cancel' ?></button>
                <button type="button" class="btn btn-primary" onclick="changePriority()"><?= $translations['save_changes'] ?? 'Save Changes' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $translations['change_status'] ?? 'Change Status' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newStatus" class="form-label"><?= $translations['status'] ?? 'Status' ?></label>
                    <select class="form-select" id="newStatus">
                        <option value="open"><?= $translations['open'] ?? 'Open' ?></option>
                        <option value="in_progress"><?= $translations['in_progress'] ?? 'In Progress' ?></option>
                        <option value="waiting_customer"><?= $translations['waiting_customer'] ?? 'Waiting Customer' ?></option>
                        <option value="waiting_admin"><?= $translations['waiting_admin'] ?? 'Waiting Admin' ?></option>
                        <option value="resolved"><?= $translations['resolved'] ?? 'Resolved' ?></option>
                        <option value="closed"><?= $translations['closed'] ?? 'Closed' ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $translations['cancel'] ?? 'Cancel' ?></button>
                <button type="button" class="btn btn-primary" onclick="changeStatus()"><?= $translations['save_changes'] ?? 'Save Changes' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statisticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $translations['statistics'] ?? 'Statistics' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statisticsContent">
                <!-- Statistics will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentTicketId = null;
let currentPage = 1;
let selectedTickets = new Set();

// Initialize module
document.addEventListener('DOMContentLoaded', function() {
    loadTickets();
    loadCustomers();
});

// Load tickets
function loadTickets(page = 1) {
    currentPage = page;
    const status = document.getElementById('statusFilter').value;
    const priority = document.getElementById('priorityFilter').value;
    const search = document.getElementById('searchInput').value;
    
    const tbody = document.getElementById('ticketsTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'get_tickets',
            page: page,
            status: status,
            priority: priority,
            search: search
        },
        success: function(response) {
            if (response.success) {
                renderTickets(response.data.tickets);
                renderPagination(response.data.pagination);
            } else {
                showAlert('error', response.error || 'Failed to load tickets');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Render tickets table
function renderTickets(tickets) {
    const tbody = document.getElementById('ticketsTableBody');
    
    if (tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No tickets found</td></tr>';
        return;
    }
    
    tbody.innerHTML = tickets.map(ticket => `
        <tr>
            <td>
                <input type="checkbox" class="ticket-checkbox" value="${ticket.id}" onchange="toggleTicketSelection(${ticket.id})">
            </td>
            <td><strong>${ticket.ticket_number}</strong></td>
            <td>
                <div>
                    <strong>${ticket.first_name} ${ticket.last_name}</strong><br>
                    <small class="text-muted">${ticket.customer_email}</small>
                </div>
            </td>
            <td>
                <div>
                    <strong>${escapeHtml(ticket.subject)}</strong><br>
                    <small class="text-muted">${escapeHtml(ticket.message.substring(0, 50))}${ticket.message.length > 50 ? '...' : ''}</small>
                </div>
            </td>
            <td>
                <span class="badge bg-${getPriorityColor(ticket.priority)}">${ticket.priority}</span>
            </td>
            <td>
                <span class="badge bg-${getStatusColor(ticket.status)}">${ticket.status}</span>
            </td>
            <td>
                <small>${formatDate(ticket.created_at)}</small>
            </td>
            <td>
                <small>${formatDate(ticket.updated_at)}</small>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="viewTicket(${ticket.id})" title="View">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="showPriorityModal(${ticket.id})" title="Change Priority">
                        <i class="bi bi-flag"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteTicket(${ticket.id})" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Helper functions
function getPriorityColor(priority) {
    const colors = {
        'low': 'success',
        'medium': 'warning',
        'high': 'danger',
        'urgent': 'dark'
    };
    return colors[priority] || 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'open': 'primary',
        'in_progress': 'info',
        'waiting_customer': 'warning',
        'waiting_admin': 'secondary',
        'resolved': 'success',
        'closed': 'dark'
    };
    return colors[status] || 'secondary';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Pagination
function renderPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    
    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    // Previous button
    if (pagination.page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTickets(${pagination.page - 1})">Previous</a></li>`;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === pagination.page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTickets(${i})">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.page < pagination.pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTickets(${pagination.page + 1})">Next</a></li>`;
    }
    
    html += '</ul>';
    container.innerHTML = html;
}

// Ticket selection
function toggleTicketSelection(ticketId) {
    if (selectedTickets.has(ticketId)) {
        selectedTickets.delete(ticketId);
    } else {
        selectedTickets.add(ticketId);
    }
    
    updateBulkActions();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.ticket-checkbox');
    
    if (selectAll.checked) {
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
            selectedTickets.add(parseInt(checkbox.value));
        });
    } else {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            selectedTickets.delete(parseInt(checkbox.value));
        });
    }
    
    updateBulkActions();
}

function updateBulkActions() {
    const bulkCard = document.getElementById('bulkActionsCard');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedTickets.size > 0) {
        bulkCard.style.display = 'block';
        selectedCount.textContent = selectedTickets.size;
    } else {
        bulkCard.style.display = 'none';
    }
}

// View ticket
function viewTicket(ticketId) {
    currentTicketId = ticketId;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'get_ticket',
            ticket_id: ticketId
        },
        success: function(response) {
            if (response.success) {
                renderTicketDetails(response.data);
                $('#viewTicketModal').modal('show');
            } else {
                showAlert('error', response.error || 'Failed to load ticket');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

function renderTicketDetails(ticket) {
    // Ticket info
    document.getElementById('ticketInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>Ticket Number:</strong> ${ticket.ticket_number}</p>
                <p><strong>Customer:</strong> ${ticket.first_name} ${ticket.last_name}</p>
                <p><strong>Email:</strong> ${ticket.customer_email}</p>
                <p><strong>Phone:</strong> ${ticket.phone || '-'}</p>
                <p><strong>Company:</strong> ${ticket.company || '-'}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Priority:</strong> <span class="badge bg-${getPriorityColor(ticket.priority)}">${ticket.priority}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(ticket.status)}">${ticket.status}</span></p>
                <p><strong>Created:</strong> ${formatDate(ticket.created_at)}</p>
                <p><strong>Updated:</strong> ${formatDate(ticket.updated_at)}</p>
                <p><strong>Replies:</strong> ${ticket.replies_count || 0}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <p><strong>Subject:</strong> ${escapeHtml(ticket.subject)}</p>
                <p><strong>Message:</strong></p>
                <div class="border rounded p-3 bg-light">${escapeHtml(ticket.message)}</div>
            </div>
        </div>
    `;
    
    // Replies
    const repliesContainer = document.getElementById('ticketReplies');
    if (ticket.replies && ticket.replies.length > 0) {
        repliesContainer.innerHTML = ticket.replies.map(reply => `
            <div class="border rounded p-3 mb-3 ${reply.is_internal ? 'bg-warning bg-opacity-10' : 'bg-light'}">
                <div class="d-flex justify-content-between">
                    <strong>${reply.author_name}</strong>
                    <small class="text-muted">${formatDate(reply.created_at)}</small>
                </div>
                ${reply.is_internal ? '<span class="badge bg-warning">Internal</span>' : ''}
                <div class="mt-2">${escapeHtml(reply.message)}</div>
            </div>
        `).join('');
    } else {
        repliesContainer.innerHTML = '<p class="text-muted">No replies yet.</p>';
    }
}

// Create ticket
function showCreateTicketModal() {
    $('#createTicketModal').modal('show');
}

function createTicket() {
    const form = document.getElementById('createTicketForm');
    const formData = new FormData(form);
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'create_ticket',
            ...Object.fromEntries(formData)
        },
        success: function(response) {
            if (response.success) {
                $('#createTicketModal').modal('hide');
                form.reset();
                loadTickets();
                showAlert('success', 'Ticket created successfully');
            } else {
                showAlert('error', response.error || 'Failed to create ticket');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Load customers for create ticket form
function loadCustomers() {
    // This would typically load from a customer endpoint
    // For now, we'll use a placeholder
    const select = document.getElementById('customerSelect');
    select.innerHTML = '<option value="">Select Customer</option>';
}

// Reply to ticket
function showReplyModal() {
    document.getElementById('replyMessage').value = '';
    $('#replyModal').modal('show');
}

function sendReply() {
    const message = document.getElementById('replyMessage').value.trim();
    
    if (!message) {
        showAlert('error', 'Message is required');
        return;
    }
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'reply_ticket',
            ticket_id: currentTicketId,
            message: message
        },
        success: function(response) {
            if (response.success) {
                $('#replyModal').modal('hide');
                viewTicket(currentTicketId); // Reload ticket details
                loadTickets(); // Refresh tickets list
                showAlert('success', 'Reply sent successfully');
            } else {
                showAlert('error', response.error || 'Failed to send reply');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Change priority
function showPriorityModal(ticketId = null) {
    if (ticketId) currentTicketId = ticketId;
    $('#priorityModal').modal('show');
}

function changePriority() {
    const priority = document.getElementById('newPriority').value;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'change_priority',
            ticket_id: currentTicketId,
            priority: priority
        },
        success: function(response) {
            if (response.success) {
                $('#priorityModal').modal('hide');
                if (currentTicketId) {
                    viewTicket(currentTicketId);
                }
                loadTickets();
                showAlert('success', 'Priority changed successfully');
            } else {
                showAlert('error', response.error || 'Failed to change priority');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Change status
function showStatusModal(ticketId = null) {
    if (ticketId) currentTicketId = ticketId;
    $('#statusModal').modal('show');
}

function changeStatus() {
    const status = document.getElementById('newStatus').value;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'change_status',
            ticket_id: currentTicketId,
            status: status
        },
        success: function(response) {
            if (response.success) {
                $('#statusModal').modal('hide');
                if (currentTicketId) {
                    viewTicket(currentTicketId);
                }
                loadTickets();
                showAlert('success', 'Status changed successfully');
            } else {
                showAlert('error', response.error || 'Failed to change status');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Close ticket
function closeTicket() {
    if (!confirm('Are you sure you want to close this ticket?')) return;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'close_ticket',
            ticket_id: currentTicketId
        },
        success: function(response) {
            if (response.success) {
                $('#viewTicketModal').modal('hide');
                loadTickets();
                showAlert('success', 'Ticket closed successfully');
            } else {
                showAlert('error', response.error || 'Failed to close ticket');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Delete ticket
function deleteTicket(ticketId = null) {
    const id = ticketId || currentTicketId;
    if (!confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) return;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'delete_ticket',
            ticket_id: id
        },
        success: function(response) {
            if (response.success) {
                if (ticketId) {
                    loadTickets();
                } else {
                    $('#viewTicketModal').modal('hide');
                    loadTickets();
                }
                showAlert('success', 'Ticket deleted successfully');
            } else {
                showAlert('error', response.error || 'Failed to delete ticket');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Add internal note
function addInternalNote() {
    const note = document.getElementById('internalNote').value.trim();
    
    if (!note) {
        showAlert('error', 'Note is required');
        return;
    }
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'add_internal_note',
            ticket_id: currentTicketId,
            note: note
        },
        success: function(response) {
            if (response.success) {
                document.getElementById('internalNote').value = '';
                viewTicket(currentTicketId);
                showAlert('success', 'Internal note added successfully');
            } else {
                showAlert('error', response.error || 'Failed to add internal note');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Bulk actions
function bulkAction(action) {
    if (selectedTickets.size === 0) {
        showAlert('error', 'No tickets selected');
        return;
    }
    
    let data = {
        plugin: 'support-tickets',
        action: 'bulk_action',
        ticket_ids: Array.from(selectedTickets),
        bulk_action: action
    };
    
    // Add additional data based on action
    if (action === 'change_priority') {
        const priority = prompt('Enter new priority (low/medium/high/urgent):');
        if (!priority) return;
        data.priority = priority;
    } else if (action === 'change_status') {
        const status = prompt('Enter new status (open/in_progress/waiting_customer/waiting_admin/resolved/closed):');
        if (!status) return;
        data.status = status;
    }
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: data,
        success: function(response) {
            if (response.success) {
                selectedTickets.clear();
                updateBulkActions();
                loadTickets();
                showAlert('success', response.data || 'Bulk action completed successfully');
            } else {
                showAlert('error', response.error || 'Failed to perform bulk action');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

// Load statistics
function loadStatistics() {
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            plugin: 'support-tickets',
            action: 'get_statistics'
        },
        success: function(response) {
            if (response.success) {
                renderStatistics(response.data);
                $('#statisticsModal').modal('show');
            } else {
                showAlert('error', response.error || 'Failed to load statistics');
            }
        },
        error: function() {
            showAlert('error', 'Network error occurred');
        }
    });
}

function renderStatistics(stats) {
    document.getElementById('statisticsContent').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Total Tickets</h6>
                        <h3>${stats.total}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Open Tickets</h6>
                        <h3>${stats.open}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Closed (30 days)</h6>
                        <h3>${stats.closed_30_days}</h3>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Utility functions
function showAlert(type, message) {
    // You can implement your own alert system here
    alert(message);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>
