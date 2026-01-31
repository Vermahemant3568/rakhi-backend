@extends('admin.layouts.app')

@section('title', 'AI Models - Model Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-robot"></i> AI Models</h4>
                    <button class="btn btn-success" onclick="showCreateModal()">
                        <i class="fas fa-plus"></i> Add Model
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Only one model can be active at a time. Activating a model will automatically deactivate others.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Provider</th>
                                    <th>Model Name</th>
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

<!-- Create Model Modal -->
<div class="modal fade" id="modelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add AI Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="modelForm">
                    <div class="mb-3">
                        <label class="form-label">Provider</label>
                        <select class="form-control" id="modelProvider">
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI</option>
                            <option value="claude">Anthropic Claude</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Model Name</label>
                        <input type="text" class="form-control" id="modelName" 
                               placeholder="e.g., gemini-1.5-pro, gpt-4, claude-3">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveModel()">Add Model</button>
            </div>
        </div>
    </div>
</div>

<script>
let models = [];

document.addEventListener('DOMContentLoaded', function() {
    loadModels();
});

function loadModels() {
    fetch('/admin/ai-models')
        .then(response => response.json())
        .then(data => {
            models = data;
            renderModels();
        });
}

function renderModels() {
    const tbody = document.getElementById('models-table');
    tbody.innerHTML = '';
    
    models.forEach(model => {
        const row = `
            <tr ${model.is_active ? 'class="table-success"' : ''}>
                <td>
                    <span class="badge bg-primary">${model.provider}</span>
                </td>
                <td><strong>${model.model_name}</strong></td>
                <td>
                    <span class="badge ${model.is_active ? 'bg-success' : 'bg-secondary'}">
                        ${model.is_active ? 'ACTIVE' : 'Inactive'}
                    </span>
                </td>
                <td>
                    ${!model.is_active ? `
                        <button class="btn btn-sm btn-success" onclick="activateModel(${model.id})">
                            <i class="fas fa-play"></i> Activate
                        </button>
                    ` : `
                        <span class="text-success"><i class="fas fa-check"></i> Currently Active</span>
                    `}
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function showCreateModal() {
    document.getElementById('modelForm').reset();
    new bootstrap.Modal(document.getElementById('modelModal')).show();
}

function saveModel() {
    const data = {
        provider: document.getElementById('modelProvider').value,
        model_name: document.getElementById('modelName').value
    };
    
    fetch('/admin/ai-models', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modelModal')).hide();
        loadModels();
        alert('Model added successfully!');
    });
}

function activateModel(id) {
    if (confirm('This will deactivate the current model and activate the selected one. Continue?')) {
        fetch(`/admin/ai-models/${id}/activate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            loadModels();
            alert('Model activated successfully!');
        });
    }
}
</script>
@endsection