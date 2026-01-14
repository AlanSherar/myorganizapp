<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Estado</th>
                        <th>Tarea</th>
                        <th>Prioridad</th>
                        <th>Tipo</th>
                        <th>Fecha/Hora</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    @php
                        $rowClass = '';
                        $now = now();

                        if ($task->status === 'completed') {
                            $rowClass = 'table-success';
                        } elseif ($task->due_date && $now->greaterThan($task->due_date)) {
                            $rowClass = 'table-danger';
                        } elseif ($task->expected_end_date && $now->greaterThan($task->expected_end_date)) {
                            $rowClass = 'table-warning';
                        } elseif ($task->start_date && $now->greaterThan($task->start_date)) {
                            $rowClass = 'table-info';
                        }
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="ps-4">
                            <form action="{{ route('tasks.toggle', $task) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $task->status === 'completed' ? 'btn-success' : 'btn-outline-secondary' }} rounded-circle" style="width: 32px; height: 32px; padding: 0;">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="fw-bold {{ $task->status === 'completed' ? 'text-decoration-line-through' : '' }}">
                                {{ $task->title }}
                            </div>
                            @if($task->description)
                            <div class="small text-muted text-truncate" style="max-width: 250px;">
                                {{ $task->description }}
                            </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $badges = [
                                    'low' => 'bg-info text-dark',
                                    'medium' => 'bg-warning text-dark',
                                    'high' => 'bg-danger'
                                ];
                                $labels = [
                                    'low' => 'Baja',
                                    'medium' => 'Media',
                                    'high' => 'Alta'
                                ];
                            @endphp
                            <span class="badge {{ $badges[$task->priority] }} rounded-pill">
                                {{ $labels[$task->priority] }}
                            </span>
                        </td>
                        <td>
                            @if($task->is_routine)
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    {{ $task->routine_type === 'daily' ? 'Diaria' : 'Mensual' }}
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">
                                    Única
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($task->is_routine)
                                <div class="small">
                                    <i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($task->routine_time)->format('H:i') }}
                                    @if($task->routine_type === 'monthly')
                                        <div class="text-muted">Día {{ $task->routine_day }}</div>
                                    @endif
                                </div>
                            @else
                                @if($task->due_date)
                                    <div class="small {{ $task->due_date < now() && $task->status !== 'completed' ? 'text-danger fw-bold' : '' }}">
                                        {{ $task->due_date->format('d/m/Y H:i') }}
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick='editTask(@json($task))'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta tarea?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-check display-4 mb-3 d-block"></i>
                            No hay tareas registradas. ¡Crea una nueva!
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
