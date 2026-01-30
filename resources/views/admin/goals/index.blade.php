@extends('admin.layouts.app')

@section('title', 'Goal Manager')
@section('page-title', 'Goal Manager')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bullseye"></i> Manage Goals</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                        <i class="fas fa-plus"></i> Add Goal
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Slug</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($goals as $goal)
                                    <tr>
                                        <td>{{ $goal->id }}</td>
                                        <td>{{ $goal->title }}</td>
                                        <td>{{ $goal->slug }}</td>
                                        <td>{{ Str::limit($goal->description, 50) }}</td>
                                        <td>
                                            <span class="badge {{ $goal->is_active ? 'bg-success' : 'bg-danger' }}">
                                                {{ $goal->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editGoal({{ $goal->id }}, '{{ $goal->title }}', '{{ $goal->description }}')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.goals.update', $goal->id) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="title" value="{{ $goal->title }}">
                                                <input type="hidden" name="description" value="{{ $goal->description }}">
                                                <input type="hidden" name="is_active" value="{{ $goal->is_active ? 0 : 1 }}">
                                                <button type="submit" class="btn btn-sm {{ $goal->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                                    <i class="fas {{ $goal->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.goals.destroy', $goal->id) }}" class="d-inline" onsubmit="return confirm('Are you sure?')">
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
                                        <td colspan="6" class="text-center">No goals found</td>
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

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.goals.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Goal Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Goal Modal -->
<div class="modal fade" id="editGoalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editGoalForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Goal Title</label>
                        <input type="text" class="form-control" name="title" id="editTitle" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editGoal(id, title, description) {
    document.getElementById('editGoalForm').action = `/admin/goals/${id}`;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDescription').value = description;
    new bootstrap.Modal(document.getElementById('editGoalModal')).show();
}
</script>
@endsection