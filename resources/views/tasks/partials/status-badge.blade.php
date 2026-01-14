@if($task->status === 'completed')
    <span class="badge bg-success">Completada</span>
@else
    <span class="badge bg-secondary">Pendiente</span>
@endif
