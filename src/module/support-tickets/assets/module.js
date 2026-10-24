/**
 * Support Tickets Module JavaScript
 * Handles all client-side functionality for the support tickets module
 */

class SupportTicketsModule {
    constructor() {
        this.currentTicketId = null;
        this.currentPage = 1;
        this.selectedTickets = new Set();
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadTickets();
        this.loadCustomers();
    }

    bindEvents() {
        // Filter events
        document.getElementById('statusFilter')?.addEventListener('change', () => this.loadTickets());
        document.getElementById('priorityFilter')?.addEventListener('change', () => this.loadTickets());
        
        // Search with debounce
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => this.loadTickets(), 500));
        }

        // Modal events
        this.bindModalEvents();
    }

    bindModalEvents() {
        // Create ticket modal
        const createModal = document.getElementById('createTicketModal');
        if (createModal) {
            createModal.addEventListener('hidden.bs.modal', () => {
                document.getElementById('createTicketForm')?.reset();
            });
        }

        // View ticket modal
        const viewModal = document.getElementById('viewTicketModal');
        if (viewModal) {
            viewModal.addEventListener('hidden.bs.modal', () => {
                this.currentTicketId = null;
            });
        }
    }

    // Load tickets from server
    loadTickets(page = 1) {
        this.currentPage = page;
        const status = document.getElementById('statusFilter')?.value || '';
        const priority = document.getElementById('priorityFilter')?.value || '';
        const search = document.getElementById('searchInput')?.value || '';
        
        const tbody = document.getElementById('ticketsTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';
        }
        
        this.ajaxRequest('get_tickets', {
            page: page,
            status: status,
            priority: priority,
            search: search
        }).then(response => {
            if (response.success) {
                this.renderTickets(response.data.tickets);
                this.renderPagination(response.data.pagination);
            } else {
                this.showAlert('error', response.error || 'Failed to load tickets');
            }
        }).catch(error => {
            this.showAlert('error', 'Network error occurred');
            console.error('Error loading tickets:', error);
        });
    }

    // Render tickets table
    renderTickets(tickets) {
        const tbody = document.getElementById('ticketsTableBody');
        if (!tbody) return;
        
        if (tickets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No tickets found</td></tr>';
            return;
        }
        
        tbody.innerHTML = tickets.map(ticket => `
            <tr class="fade-in">
                <td>
                    <input type="checkbox" class="ticket-checkbox" value="${ticket.id}" onchange="supportTicketsModule.toggleTicketSelection(${ticket.id})">
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
                        <strong>${this.escapeHtml(ticket.subject)}</strong><br>
                        <small class="text-muted">${this.escapeHtml(ticket.message.substring(0, 50))}${ticket.message.length > 50 ? '...' : ''}</small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${this.getPriorityColor(ticket.priority)}">${ticket.priority}</span>
                </td>
                <td>
                    <span class="badge bg-${this.getStatusColor(ticket.status)}">${ticket.status}</span>
                </td>
                <td>
                    <small>${this.formatDate(ticket.created_at)}</small>
                </td>
                <td>
                    <small>${this.formatDate(ticket.updated_at)}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="supportTicketsModule.viewTicket(${ticket.id})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="supportTicketsModule.showPriorityModal(${ticket.id})" title="Change Priority">
                            <i class="bi bi-flag"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="supportTicketsModule.deleteTicket(${ticket.id})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Render pagination
    renderPagination(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        if (pagination.pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination justify-content-center">';
        
        // Previous button
        if (pagination.page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="supportTicketsModule.loadTickets(${pagination.page - 1})">Previous</a></li>`;
        }
        
        // Page numbers
        for (let i = 1; i <= pagination.pages; i++) {
            if (i === pagination.page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="supportTicketsModule.loadTickets(${i})">${i}</a></li>`;
            }
        }
        
        // Next button
        if (pagination.page < pagination.pages) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="supportTicketsModule.loadTickets(${pagination.page + 1})">Next</a></li>`;
        }
        
        html += '</ul>';
        container.innerHTML = html;
    }

    // View ticket details
    viewTicket(ticketId) {
        this.currentTicketId = ticketId;
        
        this.ajaxRequest('get_ticket', { ticket_id: ticketId })
            .then(response => {
                if (response.success) {
                    this.renderTicketDetails(response.data);
                    this.showModal('viewTicketModal');
                } else {
                    this.showAlert('error', response.error || 'Failed to load ticket');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error loading ticket:', error);
            });
    }

    // Render ticket details
    renderTicketDetails(ticket) {
        // Ticket info
        const ticketInfo = document.getElementById('ticketInfo');
        if (ticketInfo) {
            ticketInfo.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Ticket Number:</strong> ${ticket.ticket_number}</p>
                        <p><strong>Customer:</strong> ${ticket.first_name} ${ticket.last_name}</p>
                        <p><strong>Email:</strong> ${ticket.customer_email}</p>
                        <p><strong>Phone:</strong> ${ticket.phone || '-'}</p>
                        <p><strong>Company:</strong> ${ticket.company || '-'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Priority:</strong> <span class="badge bg-${this.getPriorityColor(ticket.priority)}">${ticket.priority}</span></p>
                        <p><strong>Status:</strong> <span class="badge bg-${this.getStatusColor(ticket.status)}">${ticket.status}</span></p>
                        <p><strong>Created:</strong> ${this.formatDate(ticket.created_at)}</p>
                        <p><strong>Updated:</strong> ${this.formatDate(ticket.updated_at)}</p>
                        <p><strong>Replies:</strong> ${ticket.replies_count || 0}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Subject:</strong> ${this.escapeHtml(ticket.subject)}</p>
                        <p><strong>Message:</strong></p>
                        <div class="border rounded p-3 bg-light">${this.escapeHtml(ticket.message)}</div>
                    </div>
                </div>
            `;
        }
        
        // Replies
        const repliesContainer = document.getElementById('ticketReplies');
        if (repliesContainer) {
            if (ticket.replies && ticket.replies.length > 0) {
                repliesContainer.innerHTML = ticket.replies.map(reply => `
                    <div class="border rounded p-3 mb-3 ${reply.is_internal ? 'bg-warning bg-opacity-10' : 'bg-light'}">
                        <div class="d-flex justify-content-between">
                            <strong>${reply.author_name}</strong>
                            <small class="text-muted">${this.formatDate(reply.created_at)}</small>
                        </div>
                        ${reply.is_internal ? '<span class="badge bg-warning">Internal</span>' : ''}
                        <div class="mt-2">${this.escapeHtml(reply.message)}</div>
                    </div>
                `).join('');
            } else {
                repliesContainer.innerHTML = '<p class="text-muted">No replies yet.</p>';
            }
        }
    }

    // Create ticket
    showCreateTicketModal() {
        this.showModal('createTicketModal');
    }

    createTicket() {
        const form = document.getElementById('createTicketForm');
        if (!form) return;
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        this.ajaxRequest('create_ticket', data)
            .then(response => {
                if (response.success) {
                    this.hideModal('createTicketModal');
                    form.reset();
                    this.loadTickets();
                    this.showAlert('success', 'Ticket created successfully');
                } else {
                    this.showAlert('error', response.error || 'Failed to create ticket');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error creating ticket:', error);
            });
    }

    // Reply to ticket
    showReplyModal() {
        document.getElementById('replyMessage').value = '';
        document.getElementById('isInternalReply').checked = false;
        this.showModal('replyModal');
    }

    sendReply() {
        const message = document.getElementById('replyMessage')?.value.trim();
        const isInternal = document.getElementById('isInternalReply')?.checked;
        
        if (!message) {
            this.showAlert('error', 'Message is required');
            return;
        }
        
        this.ajaxRequest('reply_ticket', {
            ticket_id: this.currentTicketId,
            message: message,
            is_internal: isInternal
        }).then(response => {
            if (response.success) {
                this.hideModal('replyModal');
                this.viewTicket(this.currentTicketId);
                this.loadTickets();
                this.showAlert('success', 'Reply sent successfully');
            } else {
                this.showAlert('error', response.error || 'Failed to send reply');
            }
        }).catch(error => {
            this.showAlert('error', 'Network error occurred');
            console.error('Error sending reply:', error);
        });
    }

    // Change priority
    showPriorityModal(ticketId = null) {
        if (ticketId) this.currentTicketId = ticketId;
        this.showModal('priorityModal');
    }

    changePriority() {
        const priority = document.getElementById('newPriority')?.value;
        
        this.ajaxRequest('change_priority', {
            ticket_id: this.currentTicketId,
            priority: priority
        }).then(response => {
            if (response.success) {
                this.hideModal('priorityModal');
                if (this.currentTicketId) {
                    this.viewTicket(this.currentTicketId);
                }
                this.loadTickets();
                this.showAlert('success', 'Priority changed successfully');
            } else {
                this.showAlert('error', response.error || 'Failed to change priority');
            }
        }).catch(error => {
            this.showAlert('error', 'Network error occurred');
            console.error('Error changing priority:', error);
        });
    }

    // Change status
    showStatusModal(ticketId = null) {
        if (ticketId) this.currentTicketId = ticketId;
        this.showModal('statusModal');
    }

    changeStatus() {
        const status = document.getElementById('newStatus')?.value;
        
        this.ajaxRequest('change_status', {
            ticket_id: this.currentTicketId,
            status: status
        }).then(response => {
            if (response.success) {
                this.hideModal('statusModal');
                if (this.currentTicketId) {
                    this.viewTicket(this.currentTicketId);
                }
                this.loadTickets();
                this.showAlert('success', 'Status changed successfully');
            } else {
                this.showAlert('error', response.error || 'Failed to change status');
            }
        }).catch(error => {
            this.showAlert('error', 'Network error occurred');
            console.error('Error changing status:', error);
        });
    }

    // Close ticket
    closeTicket() {
        if (!confirm('Are you sure you want to close this ticket?')) return;
        
        this.ajaxRequest('close_ticket', { ticket_id: this.currentTicketId })
            .then(response => {
                if (response.success) {
                    this.hideModal('viewTicketModal');
                    this.loadTickets();
                    this.showAlert('success', 'Ticket closed successfully');
                } else {
                    this.showAlert('error', response.error || 'Failed to close ticket');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error closing ticket:', error);
            });
    }

    // Delete ticket
    deleteTicket(ticketId = null) {
        const id = ticketId || this.currentTicketId;
        if (!confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) return;
        
        this.ajaxRequest('delete_ticket', { ticket_id: id })
            .then(response => {
                if (response.success) {
                    if (ticketId) {
                        this.loadTickets();
                    } else {
                        this.hideModal('viewTicketModal');
                        this.loadTickets();
                    }
                    this.showAlert('success', 'Ticket deleted successfully');
                } else {
                    this.showAlert('error', response.error || 'Failed to delete ticket');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error deleting ticket:', error);
            });
    }

    // Add internal note
    addInternalNote() {
        const note = document.getElementById('internalNote')?.value.trim();
        
        if (!note) {
            this.showAlert('error', 'Note is required');
            return;
        }
        
        this.ajaxRequest('add_internal_note', {
            ticket_id: this.currentTicketId,
            note: note
        }).then(response => {
            if (response.success) {
                document.getElementById('internalNote').value = '';
                this.viewTicket(this.currentTicketId);
                this.showAlert('success', 'Internal note added successfully');
            } else {
                this.showAlert('error', response.error || 'Failed to add internal note');
            }
        }).catch(error => {
            this.showAlert('error', 'Network error occurred');
            console.error('Error adding internal note:', error);
        });
    }

    // Bulk actions
    toggleTicketSelection(ticketId) {
        if (this.selectedTickets.has(ticketId)) {
            this.selectedTickets.delete(ticketId);
        } else {
            this.selectedTickets.add(ticketId);
        }
        this.updateBulkActions();
    }

    toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.ticket-checkbox');
        
        if (selectAll?.checked) {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                this.selectedTickets.add(parseInt(checkbox.value));
            });
        } else {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                this.selectedTickets.delete(parseInt(checkbox.value));
            });
        }
        this.updateBulkActions();
    }

    updateBulkActions() {
        const bulkCard = document.getElementById('bulkActionsCard');
        const selectedCount = document.getElementById('selectedCount');
        
        if (bulkCard && selectedCount) {
            if (this.selectedTickets.size > 0) {
                bulkCard.style.display = 'block';
                selectedCount.textContent = this.selectedTickets.size;
            } else {
                bulkCard.style.display = 'none';
            }
        }
    }

    bulkAction(action) {
        if (this.selectedTickets.size === 0) {
            this.showAlert('error', 'No tickets selected');
            return;
        }
        
        let data = {
            ticket_ids: Array.from(this.selectedTickets),
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
        
        this.ajaxRequest('bulk_action', data)
            .then(response => {
                if (response.success) {
                    this.selectedTickets.clear();
                    this.updateBulkActions();
                    this.loadTickets();
                    this.showAlert('success', response.data || 'Bulk action completed successfully');
                } else {
                    this.showAlert('error', response.error || 'Failed to perform bulk action');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error performing bulk action:', error);
            });
    }

    // Load statistics
    loadStatistics() {
        this.ajaxRequest('get_statistics')
            .then(response => {
                if (response.success) {
                    this.renderStatistics(response.data);
                    this.showModal('statisticsModal');
                } else {
                    this.showAlert('error', response.error || 'Failed to load statistics');
                }
            })
            .catch(error => {
                this.showAlert('error', 'Network error occurred');
                console.error('Error loading statistics:', error);
            });
    }

    renderStatistics(stats) {
        const container = document.getElementById('statisticsContent');
        if (!container) return;
        
        container.innerHTML = `
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

    // Load customers for create ticket form
    loadCustomers() {
        // This would typically load from a customer endpoint
        // For now, we'll use a placeholder
        const select = document.getElementById('customerSelect');
        if (select) {
            select.innerHTML = '<option value="">Select Customer</option>';
        }
    }

    // Helper methods
    getPriorityColor(priority) {
        const colors = {
            'low': 'success',
            'medium': 'warning',
            'high': 'danger',
            'urgent': 'dark'
        };
        return colors[priority] || 'secondary';
    }

    getStatusColor(status) {
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

    formatDate(dateString) {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // AJAX helper
    ajaxRequest(action, data = {}) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: 'index.php',
                method: 'POST',
                data: {
                    plugin: 'support-tickets',
                    action: action,
                    ...data
                },
                success: function(response) {
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    reject(error);
                }
            });
        });
    }

    // Modal helpers
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    }

    // Alert helper
    showAlert(type, message) {
        // You can implement your own alert system here
        if (type === 'success') {
            alert('Success: ' + message);
        } else {
            alert('Error: ' + message);
        }
    }

    // Debounce helper
    debounce(func, wait) {
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
}

// Initialize module when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.supportTicketsModule = new SupportTicketsModule();
});

// Global functions for onclick handlers
function loadTickets(page) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.loadTickets(page);
    }
}

function viewTicket(ticketId) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.viewTicket(ticketId);
    }
}

function showCreateTicketModal() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.showCreateTicketModal();
    }
}

function createTicket() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.createTicket();
    }
}

function showReplyModal() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.showReplyModal();
    }
}

function sendReply() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.sendReply();
    }
}

function showPriorityModal(ticketId) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.showPriorityModal(ticketId);
    }
}

function changePriority() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.changePriority();
    }
}

function showStatusModal(ticketId) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.showStatusModal(ticketId);
    }
}

function changeStatus() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.changeStatus();
    }
}

function closeTicket() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.closeTicket();
    }
}

function deleteTicket(ticketId) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.deleteTicket(ticketId);
    }
}

function addInternalNote() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.addInternalNote();
    }
}

function toggleTicketSelection(ticketId) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.toggleTicketSelection(ticketId);
    }
}

function toggleSelectAll() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.toggleSelectAll();
    }
}

function bulkAction(action) {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.bulkAction(action);
    }
}

function loadStatistics() {
    if (window.supportTicketsModule) {
        window.supportTicketsModule.loadStatistics();
    }
}
