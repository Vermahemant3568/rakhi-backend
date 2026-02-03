@extends('admin.layouts.app')

@section('title', 'AI Models - Model Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-robot"></i> AI Models - Model Management</h4>
                    <div>
                        <button class="btn btn-primary" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Add Model
                        </button>
                        <div class="alert alert-success mb-0 py-2 d-inline-block ms-2">
                            <i class="fas fa-info-circle"></i> Only one model can be active at a time
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>Model Name</th>
                                    <th>API Key Status</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="models-table">
                                <!-- Models will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Model Modal -->
<div class="modal fade" id="modelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add AI Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="modelForm">
                    <div class="mb-3">
                        <label class="form-label">Provider</label>
                        <select class="form-select" id="provider" required>
                            <option value="">Select Provider</option>
                            <option value="gemini">Gemini</option>
                            <option value="openai">OpenAI</option>
                            <option value="claude">Claude</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Model Name</label>
                        <input type="text" class="form-control" id="model_name" required placeholder="e.g., gemini-2.5-flash">
                        <div class="form-text">
                            <strong>Recommended Gemini models:</strong><br>
                            • gemini-2.5-flash (Latest)<br>
                            • gemini-2.0-flash<br>
                            • gemini-flash-latest
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveModel()">Save Model</button>
            </div>
        </div>
    </div>
</div>

<script>
let models = [];
let editingId = null;

// Load models on page load
document.addEventListener('DOMContentLoaded', function() {
    loadModels();
});

function loadModels() {
    fetch('/admin/ai-models', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        models = data;
        renderModels();
    })
    .catch(error => {
        console.error('Error loading models:', error);
        document.getElementById('models-table').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading models</td></tr>';
    });
}

function renderModels() {
    const tbody = document.getElementById('models-table');
    tbody.innerHTML = '';
    
    models.forEach(model => {
        let apiKeyStatus = model.api_key_configured ? 'Configured' : 'Not Configured';
        let apiKeyClass = model.api_key_configured ? 'bg-success' : 'bg-danger';
        
        const row = `
            <tr>
                <td><span class="badge bg-primary">${model.provider}</span></td>
                <td><strong>${model.model_name}</strong></td>
                <td><span class="badge ${apiKeyClass}">${apiKeyStatus}</span></td>
                <td>
                    <span class="badge ${model.is_active ? 'bg-success' : 'bg-secondary'}">
                        ${model.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    ${!model.is_active && model.api_key_configured ? `
                        <button class="btn btn-sm btn-success" onclick="activateModel(${model.id})">
                            <i class="fas fa-play"></i> Activate
                        </button>
                    ` : model.is_active ? `
                        <span class="text-success"><i class="fas fa-check"></i> Currently Active</span>
                    ` : `
                        <span class="text-muted"><i class="fas fa-key"></i> API Key Required</span>
                    `}
                    <button class="btn btn-sm btn-outline-primary ms-1" onclick="editModel(${model.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${!model.is_active ? `
                        <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteModel(${model.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function showAddModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent = 'Add AI Model';
    document.getElementById('modelForm').reset();
    new bootstrap.Modal(document.getElementById('modelModal')).show();
}

function editModel(id) {
    const model = models.find(m => m.id === id);
    editingId = id;
    document.getElementById('modalTitle').textContent = 'Edit AI Model';
    document.getElementById('provider').value = model.provider;
    document.getElementById('model_name').value = model.model_name;
    new bootstrap.Modal(document.getElementById('modelModal')).show();
}

function saveModel() {
    const provider = document.getElementById('provider').value;
    const model_name = document.getElementById('model_name').value;
    
    if (!provider || !model_name) {
        alert('Please fill all fields');
        return;
    }
    
    const url = editingId ? `/admin/ai-models/${editingId}` : '/admin/ai-models';
    const method = editingId ? 'PUT' : 'POST';
    
    const formData = new FormData();
    formData.append('provider', provider);
    formData.append('model_name', model_name);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    if (editingId) {
        formData.append('_method', 'PUT');
    }
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === false) {
            alert('Error: ' + data.message);
        } else {
            bootstrap.Modal.getInstance(document.getElementById('modelModal')).hide();
            loadModels();
            alert(editingId ? 'Model updated successfully!' : 'Model added successfully!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving model');
    });
}

function activateModel(id) {
    if (!confirm('This will deactivate the current model and activate the selected one. Continue?')) {
        return;
    }
    
    fetch(`/admin/ai-models/${id}/activate`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === false) {
            alert('Error: ' + data.message);
        } else {
            loadModels();
            alert('Model activated successfully!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error activating model');
    });
}

function deleteModel(id) {
    if (!confirm('Are you sure you want to delete this model?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    formData.append('_method', 'DELETE');
    
    fetch(`/admin/ai-models/${id}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === false) {
            alert('Error: ' + data.message);
        } else {
            loadModels();
            alert('Model deleted successfully!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting model');
    });
}
</script>
@endsection