@extends('admin.layouts.app')

@section('title', 'Prompt Templates - AI Prompt Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-file-alt"></i> Prompt Templates</h4>
                    <button class="btn btn-success" onclick="showCreateModal()">
                        <i class="fas fa-plus"></i> New Template
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Template Preview</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="templates-table">
                                <!-- Templates will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="templateForm">
                    <input type="hidden" id="templateId">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-control" id="templateType">
                            <option value="chat">Chat</option>
                            <option value="voice">Voice</option>
                            <option value="emergency">Emergency</option>
                            <option value="disclaimer">Disclaimer</option>
                            <option value="followup">Follow-up</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template Content</label>
                        <textarea class="form-control" id="templateContent" rows="8" 
                                  placeholder="Enter your prompt template here..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="templateActive">
                        <label class="form-check-label">Active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>

<script>
let templates = [];

document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
});

function loadTemplates() {
    fetch('/admin/prompt-templates', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        templates = data;
        renderTemplates();
    })
    .catch(error => {
        console.error('Error loading templates:', error);
        document.getElementById('templates-table').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading templates</td></tr>';
    });
}

function renderTemplates() {
    const tbody = document.getElementById('templates-table');
    tbody.innerHTML = '';
    
    templates.forEach(template => {
        const preview = template.template.length > 100 ? 
                       template.template.substring(0, 100) + '...' : 
                       template.template;
        
        const row = `
            <tr>
                <td><span class="badge bg-info">${template.type}</span></td>
                <td><small>${preview}</small></td>
                <td>
                    <span class="badge ${template.is_active ? 'bg-success' : 'bg-danger'}">
                        ${template.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editTemplate(${template.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm ${template.is_active ? 'btn-warning' : 'btn-success'}" 
                            onclick="toggleTemplate(${template.id})">
                        <i class="fas fa-power-off"></i> ${template.is_active ? 'Disable' : 'Enable'}
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function showCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Template';
    document.getElementById('templateForm').reset();
    document.getElementById('templateId').value = '';
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function editTemplate(id) {
    const template = templates.find(t => t.id === id);
    document.getElementById('modalTitle').textContent = 'Edit Template';
    document.getElementById('templateId').value = template.id;
    document.getElementById('templateType').value = template.type;
    document.getElementById('templateContent').value = template.template;
    document.getElementById('templateActive').checked = template.is_active;
    
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function saveTemplate() {
    const id = document.getElementById('templateId').value;
    const data = {
        type: document.getElementById('templateType').value,
        template: document.getElementById('templateContent').value,
        is_active: document.getElementById('templateActive').checked
    };
    
    const url = id ? `/admin/prompt-templates/${id}` : '/admin/prompt-templates';
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
        bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
        loadTemplates();
        alert('Template saved successfully!');
    });
}

function toggleTemplate(id) {
    fetch(`/admin/prompt-templates/${id}/toggle`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        loadTemplates();
        alert('Template status updated!');
    });
}
</script>
@endsection