@extends('admin.layouts.app')

@section('title', 'Language Manager')
@section('page-title', 'Language Manager')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-language"></i> Manage Languages</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
                        <i class="fas fa-plus"></i> Add Language
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($languages as $language)
                                    <tr>
                                        <td>{{ $language->id }}</td>
                                        <td>{{ $language->name }}</td>
                                        <td>{{ $language->code }}</td>
                                        <td>
                                            <span class="badge {{ $language->is_active ? 'bg-success' : 'bg-danger' }}">
                                                {{ $language->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editLanguage({{ $language->id }}, '{{ $language->name }}', '{{ $language->code }}')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.languages.update', $language->id) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $language->name }}">
                                                <input type="hidden" name="code" value="{{ $language->code }}">
                                                <input type="hidden" name="is_active" value="{{ $language->is_active ? 0 : 1 }}">
                                                <button type="submit" class="btn btn-sm {{ $language->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                                    <i class="fas {{ $language->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.languages.destroy', $language->id) }}" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No languages found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Language Modal -->
<div class="modal fade" id="addLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.languages.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Language Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label">Language Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g., en, es, fr" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Language</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Language Modal -->
<div class="modal fade" id="editLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Language Name</label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCode" class="form-label">Language Code</label>
                        <input type="text" class="form-control" name="code" id="editCode" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Language</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLanguage(id, name, code) {
    document.getElementById('editForm').action = `/admin/languages/${id}`;
    document.getElementById('editName').value = name;
    document.getElementById('editCode').value = code;
    new bootstrap.Modal(document.getElementById('editLanguageModal')).show();
}
</script>
@endsection