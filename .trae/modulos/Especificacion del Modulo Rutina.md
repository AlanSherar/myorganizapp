# Especificación del Módulo: Rutina

## Descripción General
Este módulo se encarga de la gestión de hábitos y tareas recurrentes del usuario. Su objetivo es ayudar al usuario a mantener la constancia mediante el seguimiento de rutinas en diferentes periodos de tiempo.

## Alcance (Scope)

### Fase 1: Rutinas Diarias (Implementación Actual)
*   **Objetivo**: Gestionar actividades que se repiten cada 24 horas.
*   **Funcionalidades**:
    *   Creación de rutinas diarias.
    *   Listado de rutinas del día.
    *   Marcar/Desmarcar como completada.
    *   Visualización básica de progreso diario.

### Fase 2: Rutinas Semanales y Mensuales (Futuro)
*   Gestión de rutinas con periodicidad semanal o días específicos de la semana.
*   Gestión de rutinas mensuales.
*   Vistas de calendario y planificación.

## Estructura de Datos (Borrador)
*   **RoutineItem**:
    *   `id`: Identificador único.
    *   `title`: Nombre de la rutina.
    *   `description`: Descripción opcional.
    *   `frequency`: 'daily' | 'weekly' | 'monthly'.
    *   `isCompleted`: Estado para el ciclo actual.
    *   `createdAt`: Fecha de creación.

## Vistas y UI
*   **Vista Principal (Dashboard)**: Debe mostrar las rutinas diarias prioritarias.
*   **Gestión**: Formularios para agregar/editar rutinas.