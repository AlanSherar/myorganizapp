---
alwaysApply: true
---

# Workflow de respuestas (OrganizAPP)

## Regla: comandos de Git al final

Cada vez que el asistente haya realizado cambios en archivos del repositorio (código, tests, docs o scripts), debe cerrar su respuesta con un bloque **Git** que incluya:

```bash
git add .

# Opción 1 (corta)
git commit -m "Cambios realizados: <resumen corto>"

# Opción 2 (descriptiva)
git commit -m "Cambios realizados: <resumen más descriptivo>"
```

Si no hubo cambios en archivos del repositorio en esa interacción, no incluir el bloque Git.


