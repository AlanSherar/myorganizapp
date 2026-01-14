<div class="row">
    @php
        // Group tasks by time logic simply for display
        $timeSlots = [];
        foreach(range(0, 23) as $hour) {
            $timeSlots[$hour] = [];
        }

        foreach($tasks as $task) {
            $hour = null;
            if ($task->is_routine && $task->routine_time) {
                $hour = (int)\Carbon\Carbon::parse($task->routine_time)->format('H');
            } elseif ($task->due_date) {
                $hour = (int)$task->due_date->format('H');
            }
            
            if ($hour !== null) {
                $timeSlots[$hour][] = $task;
            } else {
                // Tasks without time go to "All Day" or a specific slot (e.g., -1)
                $timeSlots['any'][] = $task;
            }
        }
    @endphp

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Agenda del Día (Timebox)</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($timeSlots as $hour => $slotTasks)
                        @if($hour === 'any' && count($slotTasks) > 0)
                             <div class="list-group-item bg-light">
                                <div class="d-flex w-100 align-items-center">
                                    <div class="fw-bold me-3" style="width: 60px;">--:--</div>
                                    <div class="flex-grow-1">
                                        @foreach($slotTasks as $task)
                                            <div class="card mb-2 border-start border-4 border-secondary shadow-sm">
                                                <div class="card-body p-2 d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-0 {{ $task->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">
                                                            {{ $task->title }}
                                                        </h6>
                                                    </div>
                                                    @include('tasks.partials.status-badge', ['task' => $task])
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @elseif(is_int($hour))
                            <div class="list-group-item">
                                <div class="d-flex w-100 align-items-start">
                                    <div class="text-muted me-3 pt-2 text-end" style="width: 60px;">
                                        {{ sprintf('%02d:00', $hour) }}
                                    </div>
                                    <div class="flex-grow-1 border-start ps-3 py-2" style="min-height: 60px;">
                                        @if(empty($slotTasks))
                                            <small class="text-muted fst-italic">Libre</small>
                                        @else
                                            @foreach($slotTasks as $task)
                                                <div class="card mb-2 border-start border-4 {{ $task->priority === 'high' ? 'border-danger' : ($task->priority === 'medium' ? 'border-warning' : 'border-info') }} shadow-sm">
                                                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-0 {{ $task->status === 'completed' ? 'text-decoration-line-through text-muted' : '' }}">
                                                                {{ $task->title }}
                                                            </h6>
                                                            <small class="text-muted">
                                                                @if($task->is_routine)
                                                                    <i class="bi bi-arrow-repeat"></i> Rutina
                                                                @else
                                                                    {{ $task->due_date->format('H:i') }}
                                                                @endif
                                                            </small>
                                                        </div>
                                                        <form action="{{ route('tasks.toggle', $task) }}" method="POST">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="btn btn-sm {{ $task->status === 'completed' ? 'btn-success' : 'btn-outline-secondary' }} rounded-circle" style="width: 24px; height: 24px; padding: 0; line-height: 0;">
                                                                <i class="bi bi-check" style="font-size: 16px;"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
