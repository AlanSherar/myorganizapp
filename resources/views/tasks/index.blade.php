@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-list-check me-2"></i>Tareas
        </h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="resetTaskForm()">
            <i class="bi bi-plus-lg me-1"></i> Nueva Tarea
        </button>
    </div>

    {{-- Tabs Navigation --}}
    <ul class="nav nav-tabs mb-4" id="taskTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-content" type="button" role="tab" aria-controls="list-content" aria-selected="true">
                <i class="bi bi-list-ul me-1"></i> Lista
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timebox-tab" data-bs-toggle="tab" data-bs-target="#timebox-content" type="button" role="tab" aria-controls="timebox-content" aria-selected="false">
                <i class="bi bi-clock me-1"></i> Timebox
            </button>
        </li>
    </ul>

    {{-- Tabs Content --}}
    <div class="tab-content" id="taskTabsContent">
        {{-- List View --}}
        <div class="tab-pane fade show active" id="list-content" role="tabpanel" aria-labelledby="list-tab">
            @include('tasks.partials.list')
        </div>

        {{-- Timebox View --}}
        <div class="tab-pane fade" id="timebox-content" role="tabpanel" aria-labelledby="timebox-tab">
            @include('tasks.partials.timebox')
        </div>
    </div>
</div>

@include('tasks.partials.modal')

@endsection

@push('scripts')
<script>
    // Initialize Plugins
    document.addEventListener('DOMContentLoaded', function() {
        // Flatpickr for Date
        const dateFields = ['#due_date_date', '#start_date_date', '#expected_end_date_date'];
        dateFields.forEach(selector => {
            flatpickr(selector, {
                locale: "es",
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: updateAllHiddenDates
            });
            
            const element = document.querySelector(selector);
            if (element) {
                element.addEventListener('input', updateAllHiddenDates);
            }
        });

        // ClockPicker for Time
        $('.clockpicker').clockpicker({
            donetext: 'Listo',
            autoclose: true,
            placement: 'top',
            align: 'left',
            afterDone: function() {
                updateAllHiddenDates();
            }
        });

        // "Now" Button for Deadline
        const btnNow = document.getElementById('btn-now');
        if (btnNow) {
            btnNow.addEventListener('click', function() {
                const now = new Date();
                
                // Set Date
                const fp = document.getElementById('due_date_date')._flatpickr;
                if (fp) {
                    fp.setDate(now);
                }
                
                // Set Time
                const hh = String(now.getHours()).padStart(2, '0');
                const min = String(now.getMinutes()).padStart(2, '0');
                const timeStr = `${hh}:${min}`;
                
                document.getElementById('due_date_time').value = timeStr;
                
                // Update hidden input
                updateAllHiddenDates();
            });
        }
        
        // Listeners for time inputs
        const timeInputs = ['due_date_time', 'start_date_time', 'expected_end_date_time'];
        timeInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', updateAllHiddenDates);
            }
        });
    });

    function updateAllHiddenDates() {
        updateHiddenDate('due_date');
        updateHiddenDate('start_date');
        updateHiddenDate('expected_end_date');
    }

    function updateHiddenDate(baseId) {
        const date = document.getElementById(baseId + '_date').value;
        const time = document.getElementById(baseId + '_time').value;
        const hiddenInput = document.getElementById(baseId);
        
        if (date && time) {
            hiddenInput.value = `${date} ${time}:00`;
        } else {
            hiddenInput.value = '';
        }
    }

    function resetTaskForm() {
        document.getElementById('taskForm').reset();
        document.getElementById('taskModalLabel').textContent = 'Nueva Tarea';
        document.getElementById('taskForm').action = "{{ route('tasks.store') }}";
        document.getElementById('method_field').value = 'POST';
        
        // Reset custom fields
        const fields = ['due_date', 'start_date', 'expected_end_date'];
        fields.forEach(field => {
            document.getElementById(field + '_date').value = '';
            document.getElementById(field + '_time').value = '';
            document.getElementById(field).value = '';
        });
        
        toggleRoutineFields();
    }

    function editTask(task) {
        document.getElementById('taskModalLabel').textContent = 'Editar Tarea';
        document.getElementById('taskForm').action = `/tasks/${task.id}`;
        document.getElementById('method_field').value = 'PUT';

        document.getElementById('title').value = task.title;
        document.getElementById('description').value = task.description;
        document.getElementById('priority').value = task.priority;
        
        const isRoutine = task.is_routine;
        document.getElementById('is_routine').checked = isRoutine;
        
        toggleRoutineFields();

        if (isRoutine) {
            document.getElementById('routine_type').value = task.routine_type;
            document.getElementById('routine_time').value = task.routine_time ? task.routine_time.substring(0, 5) : '';
            if (task.routine_type === 'monthly') {
                document.getElementById('routine_day').value = task.routine_day;
            }
            toggleRoutineTypeFields();
        } else {
            // Helper to set date/time fields
            const setDateTimeFields = (dateStr, baseId) => {
                if (dateStr) {
                    const parts = dateStr.split(/[ T]/);
                    const datePart = parts[0];
                    const timePart = parts[1] ? parts[1].substring(0, 5) : '';

                    document.getElementById(baseId + '_date')._flatpickr.setDate(datePart);
                    document.getElementById(baseId + '_time').value = timePart;
                } else {
                    document.getElementById(baseId + '_date')._flatpickr.clear();
                    document.getElementById(baseId + '_time').value = '';
                }
            };

            setDateTimeFields(task.due_date, 'due_date');
            setDateTimeFields(task.start_date, 'start_date');
            setDateTimeFields(task.expected_end_date, 'expected_end_date');
            
            updateAllHiddenDates();
        }

        const modal = new bootstrap.Modal(document.getElementById('taskModal'));
        modal.show();
    }

    function toggleRoutineFields() {
        const isRoutine = document.getElementById('is_routine').checked;
        const routineFields = document.getElementById('routine-fields');
        const standardFields = document.getElementById('standard-fields');

        if (isRoutine) {
            routineFields.classList.remove('d-none');
            standardFields.classList.add('d-none');
            toggleRoutineTypeFields();
        } else {
            routineFields.classList.add('d-none');
            standardFields.classList.remove('d-none');
        }
    }

    function toggleRoutineTypeFields() {
        const type = document.getElementById('routine_type').value;
        const dayField = document.getElementById('routine-day-field');

        if (type === 'monthly') {
            dayField.classList.remove('d-none');
        } else {
            dayField.classList.add('d-none');
        }
    }

    document.getElementById('is_routine').addEventListener('change', toggleRoutineFields);
    document.getElementById('routine_type').addEventListener('change', toggleRoutineTypeFields);
</script>
@endpush
