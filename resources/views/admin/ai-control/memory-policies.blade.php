@extends('admin.layouts.app')

@section('title', 'Memory Policies - AI Memory Management')
@section('page-title', 'Memory Policies Management')

@section('content')
<div class="container-fluid">
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-brain fa-2x mb-2"></i>
                    <h4 id="totalPolicies">0</h4>
                    <small>Total Policies</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-toggle-on fa-2x mb-2"></i>
                    <h4 id="activePolicies">0</h4>
                    <small>Active Policies</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-2x mb-2"></i>
                    <h4 id="storingPolicies">0</h4>
                    <small>Storing Memory</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h4 id="highPriority">0</h4>
                    <small>High Priority</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-memory"></i> Memory Policies Configuration</h4>
            <div>
                <button class="btn btn-success btn-sm" onclick="initializeDefaults()">
                    <i class="fas fa-sync"></i> Initialize Defaults
                </button>
                <button class="btn btn-primary btn-sm" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Policy
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Memory Policies</strong> control how Rakhi stores and recalls different types of user information. 
                Higher priority memories are recalled first during conversations.
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Memory Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Storage</th>
                            <th>Priority</th>
                            <th>Retention</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="policies-table">
                        <tr><td colspan="7" class="text-center">Loading policies...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Policy Modal -->
<div class="modal fade" id="policyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Edit Memory Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="policyForm">
                    <input type="hidden" id="policyId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Memory Type *</label>
                                <input type="text" class="form-control" id="policyType" required>
                                <small class="form-text text-muted">Unique identifier for this memory type</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priority (1-10) *</label>
                                <input type="number" class="form-control" id="policyPriority" min="1" max="10" required>
                                <small class="form-text text-muted">Higher = more important</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="policyDescription" rows="2" placeholder="Describe what this memory type contains..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Retention Period (Days) *</label>
                                <input type="number" class="form-control" id="policyRetention" min="1" max="3650" required>
                                <small class="form-text text-muted">How long to keep memories (1-3650 days)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Settings</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="policyActive">
                                    <label class="form-check-label">Policy Active</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="policyStore">
                                    <label class="form-check-label">Store Memories</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePolicy()">Save Policy</button>
            </div>
        </div>
    </div>
</div>

<script>
let policies = [];
let isEditMode = false;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadPolicies();
});

function loadStats() {
    fetch('{{ route("admin.memory-policies.stats") }}', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('totalPolicies').textContent = data.total_policies || 0;
        document.getElementById('activePolicies').textContent = data.active_policies || 0;
        document.getElementById('storingPolicies').textContent = data.storing_policies || 0;
        document.getElementById('highPriority').textContent = data.high_priority || 0;
    })
    .catch(error => console.error('Error loading stats:', error));
}

function loadPolicies() {
    fetch('{{ route("admin.memory-policies.index") }}', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        policies = data;
        renderPolicies();
    })
    .catch(error => {
        console.error('Error loading policies:', error);
        document.getElementById('policies-table').innerHTML = 
            '<tr><td colspan="7" class="text-center text-danger">Error loading policies</td></tr>';
    });
}

function renderPolicies() {
    const tbody = document.getElementById('policies-table');
    tbody.innerHTML = '';
    
    if (policies.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No policies found. Click "Initialize Defaults" to create default policies.</td></tr>';
        return;
    }
    
    policies.forEach(policy => {
        const priorityBadge = getPriorityBadge(policy.priority);
        const retentionText = getRetentionText(policy.retention_days);
        
        const row = `
            <tr>
                <td>
                    <strong>${policy.type}</strong>
                </td>
                <td>
                    <small class="text-muted">${policy.description || 'No description'}</small>
                </td>
                <td>
                    <span class="badge ${policy.is_active ? 'bg-success' : 'bg-secondary'}">
                        <i class="fas ${policy.is_active ? 'fa-check' : 'fa-times'}"></i>
                        ${policy.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <span class="badge ${policy.store_memory ? 'bg-primary' : 'bg-warning'}">
                        <i class="fas ${policy.store_memory ? 'fa-database' : 'fa-ban'}"></i>
                        ${policy.store_memory ? 'Storing' : 'Not Storing'}
                    </span>
                </td>
                <td>
                    <span class="badge ${priorityBadge.class}">${policy.priority}</span>
                    <small class="text-muted d-block">${priorityBadge.label}</small>
                </td>
                <td>
                    <span class="badge bg-info">${retentionText}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editPolicy(${policy.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-${policy.is_active ? 'warning' : 'success'}" 
                                onclick="togglePolicy(${policy.id})" title="Toggle Status">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="btn btn-outline-${policy.store_memory ? 'danger' : 'info'}" 
                                onclick="toggleStorage(${policy.id})" title="Toggle Storage">
                            <i class="fas fa-database"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function getPriorityBadge(priority) {
    if (priority >= 9) return { class: 'bg-danger', label: 'Critical' };
    if (priority >= 7) return { class: 'bg-warning', label: 'High' };
    if (priority >= 5) return { class: 'bg-info', label: 'Medium' };
    return { class: 'bg-secondary', label: 'Low' };
}

function getRetentionText(days) {
    if (days >= 365) return Math.floor(days / 365) + 'y';
    if (days >= 30) return Math.floor(days / 30) + 'm';
    return days + 'd';
}

function showAddModal() {
    isEditMode = false;
    document.getElementById('modalTitle').textContent = 'Add New Memory Policy';
    document.getElementById('policyForm').reset();
    document.getElementById('policyId').value = '';
    document.getElementById('policyType').readOnly = false;
    document.getElementById('policyActive').checked = true;
    document.getElementById('policyStore').checked = true;
    document.getElementById('policyPriority').value = 5;
    document.getElementById('policyRetention').value = 365;
    
    new bootstrap.Modal(document.getElementById('policyModal')).show();
}

function editPolicy(id) {
    isEditMode = true;
    const policy = policies.find(p => p.id === id);
    
    document.getElementById('modalTitle').textContent = 'Edit Memory Policy';
    document.getElementById('policyId').value = policy.id;
    document.getElementById('policyType').value = policy.type;
    document.getElementById('policyType').readOnly = true;
    document.getElementById('policyDescription').value = policy.description || '';
    document.getElementById('policyPriority').value = policy.priority;
    document.getElementById('policyRetention').value = policy.retention_days;
    document.getElementById('policyActive').checked = policy.is_active;
    document.getElementById('policyStore').checked = policy.store_memory;
    
    new bootstrap.Modal(document.getElementById('policyModal')).show();
}

function savePolicy() {
    const id = document.getElementById('policyId').value;
    const data = {
        type: document.getElementById('policyType').value,
        description: document.getElementById('policyDescription').value,
        priority: parseInt(document.getElementById('policyPriority').value),
        retention_days: parseInt(document.getElementById('policyRetention').value),
        is_active: document.getElementById('policyActive').checked,
        store_memory: document.getElementById('policyStore').checked
    };
    
    const url = isEditMode ? 
        `{{ route('admin.memory-policies.index') }}/${id}` : 
        `{{ route('admin.memory-policies.store') }}`;
    const method = isEditMode ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('policyModal')).hide();
        loadStats();
        loadPolicies();
        showAlert('success', data.message);
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error saving policy');
    });
}

function togglePolicy(id) {
    fetch(`{{ route('admin.memory-policies.index') }}/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadStats();
        loadPolicies();
        showAlert('success', data.message);
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error toggling policy status');
    });
}

function toggleStorage(id) {
    fetch(`{{ route('admin.memory-policies.index') }}/${id}/toggle-storage`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadStats();
        loadPolicies();
        showAlert('success', data.message);
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error toggling storage setting');
    });
}

function initializeDefaults() {
    if (!confirm('This will create/update default memory policies. Continue?')) return;
    
    fetch('{{ route("admin.memory-policies.initialize") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadStats();
        loadPolicies();
        showAlert('success', data.message);
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error initializing default policies');
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>
@endsection