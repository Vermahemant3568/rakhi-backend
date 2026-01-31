@extends('admin.layouts.app')

@section('title', 'Memory Policies - Memory Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-memory"></i> Memory Policies</h4>
                    <button class="btn btn-success" onclick="showCreateModal()">
                        <i class="fas fa-plus"></i> New Policy
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Control what Rakhi remembers and stores in her memory system.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Memory Type</th>
                                    <th>Storage</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="policies-table">
                                <!-- Policies will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Policy Modal -->
<div class="modal fade" id="policyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Memory Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="policyForm">
                    <input type="hidden" id="policyId">
                    <div class="mb-3">
                        <label class="form-label">Memory Type</label>
                        <select class="form-control" id="policyType">
                            <option value="preference">Preference</option>
                            <option value="condition">Health Condition</option>
                            <option value="emotion">Emotion</option>
                            <option value="habit">Habit</option>
                            <option value="conversation">Conversation</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority (1-10)</label>
                        <input type="number" class="form-control" id="policyPriority" 
                               min="1" max="10" value="1">
                        <small class="form-text text-muted">Higher priority memories are recalled first</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="policyStore" checked>
                        <label class="form-check-label">Store this type of memory</label>
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

document.addEventListener('DOMContentLoaded', function() {
    loadPolicies();
});

function loadPolicies() {
    fetch('/admin/memory-policies')
        .then(response => response.json())
        .then(data => {
            policies = data;
            renderPolicies();
        });
}

function renderPolicies() {
    const tbody = document.getElementById('policies-table');
    tbody.innerHTML = '';
    
    policies.forEach(policy => {
        const row = `
            <tr>
                <td>
                    <span class="badge bg-info">${policy.type}</span>
                </td>
                <td>
                    <span class="badge ${policy.store ? 'bg-success' : 'bg-danger'}">
                        ${policy.store ? 'Enabled' : 'Disabled'}
                    </span>
                </td>
                <td>
                    <span class="badge bg-secondary">${policy.priority}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editPolicy(${policy.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm ${policy.store ? 'btn-warning' : 'btn-success'}" 
                            onclick="togglePolicy(${policy.id})">
                        <i class="fas fa-power-off"></i> ${policy.store ? 'Disable' : 'Enable'}
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function showCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Memory Policy';
    document.getElementById('policyForm').reset();
    document.getElementById('policyId').value = '';
    document.getElementById('policyStore').checked = true;
    new bootstrap.Modal(document.getElementById('policyModal')).show();
}

function editPolicy(id) {
    const policy = policies.find(p => p.id === id);
    document.getElementById('modalTitle').textContent = 'Edit Memory Policy';
    document.getElementById('policyId').value = policy.id;
    document.getElementById('policyType').value = policy.type;
    document.getElementById('policyPriority').value = policy.priority;
    document.getElementById('policyStore').checked = policy.store;
    
    new bootstrap.Modal(document.getElementById('policyModal')).show();
}

function savePolicy() {
    const id = document.getElementById('policyId').value;
    const data = {
        type: document.getElementById('policyType').value,
        priority: parseInt(document.getElementById('policyPriority').value),
        store: document.getElementById('policyStore').checked
    };
    
    const url = id ? `/admin/memory-policies/${id}` : '/admin/memory-policies';
    const method = id ? 'PUT' : 'POST';
    
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
        loadPolicies();
        alert('Memory policy saved successfully!');
    });
}

function togglePolicy(id) {
    fetch(`/admin/memory-policies/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadPolicies();
        alert('Memory policy updated!');
    });
}
</script>
@endsection