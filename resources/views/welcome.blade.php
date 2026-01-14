@extends('layouts.app')

@section('content')
<!-- Hero Section -->
<div class="container text-center py-5">
    <h1 class="display-4 fw-bold mb-4">
        Organiza tu vida con <span class="text-danger">OrganizApp</span>
    </h1>
    
    <p class="lead text-secondary mb-5 mx-auto" style="max-width: 700px;">
        La plataforma integral para gestionar tus tareas, proyectos y metas personales. 
        Estructura tu día a día con nuestros módulos especializados.
    </p>

    <div class="d-flex justify-content-center gap-3">
        <a href="#" class="btn btn-danger btn-lg px-4 shadow-sm">
            Comenzar Ahora
        </a>
        <a href="#modulos" class="btn btn-outline-secondary btn-lg px-4 shadow-sm">
            Ver Módulos
        </a>
    </div>
</div>

<!-- Módulos Preview -->
<div id="modulos" class="container py-5">
    <div class="row g-4">
        <!-- Módulo 1 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-calendar-check fs-3"></i>
                    </div>
                    <h3 class="h4 card-title fw-bold">Calendario Inteligente</h3>
                    <p class="card-text text-muted">Planifica tus eventos y recordatorios con una vista clara de tu tiempo.</p>
                </div>
            </div>
        </div>

        <!-- Módulo 2 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-list-task fs-3"></i>
                    </div>
                    <h3 class="h4 card-title fw-bold">Gestor de Tareas</h3>
                    <p class="card-text text-muted">Listas To-Do avanzadas con prioridades y seguimiento de progreso.</p>
                </div>
            </div>
        </div>

        <!-- Módulo 3 -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-cash-coin fs-3"></i>
                    </div>
                    <h3 class="h4 card-title fw-bold">Finanzas Personales</h3>
                    <p class="card-text text-muted">Controla tus gastos e ingresos de forma sencilla y visual.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        transition: all 0.3s ease;
    }
</style>
@endsection