{{-- Create/Edit Modal --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Nueva Tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="taskForm" method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <input type="hidden" name="_method" id="method_field" value="POST">

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Título</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Prioridad</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Baja</option>
                                <option value="medium" selected>Media</option>
                                <option value="high">Alta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="is_routine" name="is_routine" value="1">
                                <label class="form-check-label" for="is_routine">Es Rutina</label>
                            </div>
                        </div>
                    </div>

                    {{-- Routine Fields --}}
                    <div id="routine-fields" class="d-none border-start border-primary ps-3 mb-3">
                        <h6 class="text-primary mb-3">Configuración de Rutina</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="routine_type" class="form-label">Frecuencia</label>
                                <select class="form-select" id="routine_type" name="routine_type">
                                    <option value="daily">Diaria</option>
                                    <option value="monthly">Mensual</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="routine-day-field">
                                <label for="routine_day" class="form-label">Día del mes (1-31)</label>
                                <input type="number" class="form-control" id="routine_day" name="routine_day" min="1" max="31">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="routine_time" class="form-label">Hora</label>
                                <div class="input-group clockpicker" data-placement="bottom" data-align="left" data-autoclose="true">
                                    <input type="text" class="form-control bg-white" id="routine_time" name="routine_time" readonly autocomplete="off">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Standard Fields --}}
                    <div id="standard-fields">
                        {{-- Start Date --}}
                        <div class="mb-3">
                            <label class="form-label">Fecha de Inicio (Opcional)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-white" id="start_date_date" placeholder="Fecha inicio" readonly>
                                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group clockpicker" data-placement="top" data-align="left" data-autoclose="true">
                                        <input type="text" class="form-control bg-white" id="start_date_time" placeholder="Hora inicio" readonly autocomplete="off">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="start_date" name="start_date">
                        </div>

                        {{-- Expected End Date --}}
                        <div class="mb-3">
                            <label class="form-label">Fecha Esperada de Finalización (Opcional)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-white" id="expected_end_date_date" placeholder="Fecha esperada" readonly>
                                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group clockpicker" data-placement="top" data-align="left" data-autoclose="true">
                                        <input type="text" class="form-control bg-white" id="expected_end_date_time" placeholder="Hora esperada" readonly autocomplete="off">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="expected_end_date" name="expected_end_date">
                        </div>

                        {{-- Due Date --}}
                        <div class="mb-3">
                            <label class="form-label">Fecha Límite (Deadline)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-white" id="due_date_date" placeholder="Fecha límite" readonly>
                                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group clockpicker" data-placement="top" data-align="left" data-autoclose="true">
                                        <input type="text" class="form-control bg-white" id="due_date_time" placeholder="Hora límite" readonly autocomplete="off">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Hidden input for backend -->
                            <input type="hidden" id="due_date" name="due_date">
                            
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-now">
                                    <i class="bi bi-lightning-fill"></i> Usar fecha y hora actual para Deadline
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Fix for ClockPicker in Bootstrap 5 */
    .clockpicker-popover {
        z-index: 999999 !important;
        position: absolute !important;
    }
    
    /* Ensure modal doesn't clip the picker */
    .modal-body {
        overflow: visible !important;
    }
    
    /* Shim for Bootstrap 5 missing classes (popover-title/content vs header/body) */
    .clockpicker-popover .popover-title {
        background-color: #f7f7f7;
        font-weight: bold;
        padding: 8px 14px;
        border-bottom: 1px solid #ebebeb;
        border-radius: 5px 5px 0 0;
    }
    .clockpicker-popover .popover-content {
        padding: 10px;
        background-color: #fff;
    }
    
    /* Ensure pickers are on top of modal */
    .flatpickr-calendar {
        z-index: 999999 !important;
    }
    
    /* Fix input styles to match form */
    .clockpicker input {
        cursor: pointer;
    }
</style>
