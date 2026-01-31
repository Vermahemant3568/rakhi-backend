@extends('admin.layouts.app')

@section('title', 'Rakhi Rules - AI Behavior Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-brain"></i> Rakhi Rules - AI Behavior Control</h4>
                    <div class="alert alert-warning mb-0 py-2">
                        <i class="fas fa-exclamation-triangle"></i> Changes take effect immediately
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Rule Key</th>
                                    <th>Current Value</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rules-table">
                                <!-- Rules will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRuleForm">
                    <input type="hidden" id="ruleId">
                    <div class="mb-3">
                        <label class="form-label">Rule Key</label>
                        <input type="text" class="form-control" id="ruleKey" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Value</label>
                        <textarea class="form-control" id="ruleValue" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRule()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
let rules = [];

// Load rules on page load
document.addEventListener('DOMContentLoaded', function() {
    loadRules();
});

function loadRules() {
    fetch('/admin/rakhi-rules')
        .then(response => response.json())
        .then(data => {
            rules = data;
            renderRules();
        });
}

function renderRules() {
    const tbody = document.getElementById('rules-table');
    tbody.innerHTML = '';
    
    rules.forEach(rule => {
        const row = `
            <tr>
                <td><strong>${rule.key}</strong></td>
                <td>${rule.value}</td>
                <td>
                    <span class="badge ${rule.is_active ? 'bg-success' : 'bg-danger'}">
                        ${rule.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editRule(${rule.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm ${rule.is_active ? 'btn-warning' : 'btn-success'}" 
                            onclick="toggleRule(${rule.id})">
                        <i class="fas fa-power-off"></i> ${rule.is_active ? 'Disable' : 'Enable'}
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function editRule(id) {
    const rule = rules.find(r => r.id === id);
    document.getElementById('ruleId').value = rule.id;
    document.getElementById('ruleKey').value = rule.key;
    document.getElementById('ruleValue').value = rule.value;
    
    new bootstrap.Modal(document.getElementById('editRuleModal')).show();
}

function saveRule() {
    const id = document.getElementById('ruleId').value;
    const value = document.getElementById('ruleValue').value;
    
    fetch(`/admin/rakhi-rules/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ value: value })
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('editRuleModal')).hide();
        loadRules();
        alert('Rule updated successfully!');
    });
}

function toggleRule(id) {
    fetch(`/admin/rakhi-rules/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadRules();
        alert('Rule status updated!');
    });
}
</script>
@endsection