# OrganizAPP

OrganizAPP es una aplicación para ordenar tu día a día en un solo lugar:

- **Tareas**: anotar lo que tenés que hacer, con fecha, prioridad y repetición.
- **Finanzas**: registrar ingresos y gastos, y ver cuánto te queda en el mes.
- **Rutinas**: hábitos diarios (por ejemplo "Gimnasio de 9 a 10") y un puntaje de cumplimiento.
- **Inicio**: un resumen con los números más importantes de las tres secciones.

---

## Estado actual del proyecto

> Revisión: noviembre de 2026. Versión en desarrollo (todavía no es una versión final).

**En resumen:** la app se puede usar en la computadora para tareas, gastos y rutinas. Las tareas son la parte más completa. Finanzas y Rutinas funcionan en lo básico pero les faltan detalles. Todavía no tiene usuarios con contraseña y no se puede usar cómodamente desde el celular.

### Qué funciona hoy

| Función | Estado | Comentario |
|---|---|---|
| Pantalla de Inicio (resumen) | ✅ Lista | Muestra % de tareas hechas, balance del mes y puntaje de rutinas. |
| Crear y borrar tareas | ✅ Lista | Con título, prioridad y fecha/hora de vencimiento. |
| Tareas que se repiten | ✅ Lista | Diarias, semanales, mensuales o anuales. Ver la aclaración sobre el modo *Supabase* más abajo. |
| Completar una tarea (con opción de arrepentirse) | ✅ Lista | Tenés unos segundos para cancelar antes de que quede completada. |
| Tareas vencidas ("No completadas") | ✅ Lista | Las tareas que pasan su fecha se mueven a esa pestaña al abrir o recargar la app. |
| Borrar varias tareas completadas juntas | ✅ Lista | Con un aviso de confirmación antes de borrar. |
| Cargar ingresos y gastos a mano | ✅ Lista | Se pueden borrar, pero **no editar**. |
| Importar movimientos del banco | 🟡 Parcial | Solo acepta un formato muy simple (ver más abajo). Todo lo importado queda sin categoría. |
| Rutinas diarias | 🟡 Parcial | No se puede elegir qué días de la semana aplica (siempre son todos los días) y no se ve qué marcaste hoy. |
| Puntaje de disciplina | 🟡 Parcial | Solo mide el día de hoy y vuelve a empezar cada día. |
| Modo claro / oscuro | ✅ Lista | Botón "Cambiar tema". |
| Usarla desde el celular | ⏳ Pendiente | En pantallas chicas el menú lateral desaparece y no hay forma de cambiar de sección. |
| Usuarios con inicio de sesión | ⏳ Pendiente | Hoy todos los que usan la misma instalación ven los mismos datos. |
| Idioma | 🟡 Parcial | La app mezcla textos en inglés y en español. |

### Limitaciones que conviene conocer

- **No hay cuentas de usuario.** No se pide usuario ni contraseña: la app funciona como si hubiera una sola persona.
- **Dónde quedan guardados tus datos depende de cómo abras la app** (ver [Cómo abrir la app](#cómo-abrir-la-app)). En algunos casos los datos se pueden perder.
- **No hay ningún aviso al importar movimientos del banco:** si el texto no tiene el formato correcto, no se carga nada y la app no te lo dice.
- **El balance del mes** junta los movimientos del mismo mes de *cualquier* año (por ejemplo, noviembre 2025 y noviembre 2026 se suman juntos).
- **Problemas con las fechas** (porque la app calcula el día con la hora internacional, que va 3 horas adelantada respecto de Argentina):
  - en la tabla de Finanzas, cada movimiento aparece con **un día menos** que la fecha que cargaste (cargás el 15 y se ve el 14);
  - por lo mismo, un movimiento cargado el **día 1** se suma al balance del **mes anterior**;
  - **después de las 21 h** la app ya considera que es el día siguiente: lo que marques en Rutinas cuenta para mañana, el puntaje de disciplina vuelve a cero, y la fecha que viene puesta al cargar un movimiento es la de mañana.
- **Las tareas vencidas no se mueven mientras mirás la pantalla:** pasan a "No completadas" recién cuando abrís o recargás la app (tecla F5), o cuando hacés algún cambio en las tareas.
- **En el modo "Supabase Directo"** (una de las formas de guardar datos):
  - las tareas repetitivas **no** crean solas la siguiente repetición,
  - las tareas vencidas **no** pasan solas a "No completadas",
  - **importar movimientos del banco no funciona** (no carga nada),
  - el puntaje de disciplina suma todos los días en lugar de solo hoy.

### Qué falta (próximos pasos sugeridos)

1. Poder usar la app desde el celular (menú para pantallas chicas).
2. Usuarios con inicio de sesión, para que cada persona vea solo sus datos.
3. Editar ingresos y gastos, y ponerles categoría a los importados.
4. Rutinas: elegir los días de la semana y ver qué se marcó hoy.
5. Importar los archivos reales que descargan los bancos, con un mensaje de cuántos movimientos se cargaron.
6. Corregir los problemas de fechas (usar siempre la hora de Argentina).
7. Pasar toda la app a español.
8. Igualar el modo "Supabase Directo" con los otros modos.

---

## Cómo abrir la app

> Esto aplica a computadoras con Windows. La primera vez, alguien con conocimientos técnicos tiene que hacer la [instalación](#instalación) (son 2 pasos).

1. En la carpeta del proyecto, hacé **doble clic en `start_app.bat`**. Se abre una ventana negra con un menú.
2. Cuando pregunte el **modo de inicio**, escribí **`d`** y apretá Enter.
3. Cuando pregunte el **proveedor de datos** (dónde se guardan tus datos), elegí una opción:

   | Letra | Opción | Qué significa | ¿Para quién? |
   |---|---|---|---|
   | **`l`** | Local | Los datos quedan guardados **solo en este navegador de esta computadora**. No hace falta configurar nada. Si borrás los datos del navegador, o abrís la app en otro navegador o en otra computadora, no vas a ver lo que cargaste. | ✅ **Recomendado** para empezar y para uso personal. |
   | `s` | Supabase Directo | Los datos se guardan en internet (en un servicio llamado Supabase) y se pueden ver desde cualquier computadora. | Requiere que alguien técnico lo configure. Tiene las limitaciones de arriba. |
   | `r` | Remoto | Los datos los maneja un programa aparte (el "servidor") que se abre junto con la app. Después pregunta qué base de datos usar (el "archivo" donde quedan guardados los datos): `m` (MongoDB) o `s` (Supabase). | Uso técnico. **Ojo:** si elegís MongoDB y no está instalado, los datos se guardan de forma temporal y **se borran al cerrar la ventana**. |

4. Se abre el navegador con la app. Si la página aparece en blanco, esperá unos segundos y recargala (tecla F5).
5. **No cierres la ventana negra mientras usás la app**: si la cerrás, la app deja de funcionar. Para salir, cerrá el navegador y después la ventana negra.

Si más adelante abrís la app directamente desde el navegador (sin la ventana negra del paso 1), usa la última opción que elegiste en ese navegador.

### Si aparece un error al abrirla

| Mensaje en la ventana negra | Qué hacer |
|---|---|
| `Node.js NO se encontro` | Falta instalar Node.js (el programa que hace funcionar la app). Descargalo de [nodejs.org](https://nodejs.org), instalalo y reiniciá la computadora. |
| `npm NO se encontro` | Normalmente se arregla reiniciando la computadora después de instalar Node.js. Si sigue, reinstalá Node.js. |
| `No existe la carpeta "node_modules"` | Falta el paso de instalación. Ver [Instalación](#instalación). |
| `PROCESO FINALIZADO CON ERROR` | Algo falló al arrancar. Sacale una foto a la ventana y pasásela a quien mantiene la app. |

---

## Cómo usar OrganizAPP

En la parte izquierda de la pantalla está el **menú** con las cuatro secciones: **Dashboard** (Inicio), **Tasks** (Tareas), **Finance** (Finanzas) y **Routines** (Rutinas). Abajo del menú está el botón **Cambiar tema**, que alterna entre fondo claro y oscuro.

> Algunos textos de la app todavía están en inglés. Abajo los nombramos tal como aparecen en pantalla, con su traducción.

### Inicio (Dashboard)

Es la primera pantalla. Muestra tres tarjetas:

- **Task Completion** (tareas completadas): qué porcentaje de todas tus tareas ya terminaste, con un gráfico circular.
- **Monthly Balance** (balance del mes): ingresos menos gastos del mes actual. En verde si te sobra plata, en rojo si gastaste más de lo que entró.
- **Discipline Score** (puntaje de disciplina): qué porcentaje de tus rutinas marcaste como hechas hoy.

En esta pantalla no se carga nada: solo sirve para ver cómo venís.

### Tareas (Tasks)

Para anotar cosas que tenés que hacer, una sola vez o de forma repetida.

**Crear una tarea**

1. Tocá **Add Task** (Agregar tarea).
2. Completá:
   - **Title** (Título): qué tenés que hacer. Es obligatorio.
   - **Type** (Tipo): **One-time** si es una sola vez, o **Recurring** si se repite.
   - **Recurrence** (Repetición), solo para las que se repiten: **Daily** (diaria), **Weekly** (semanal), **Monthly** (mensual) o **Yearly** (anual).
   - **Priority** (Prioridad): **Low** (baja), **Medium** (media) o **High** (alta).
   - **Due Date** (Vencimiento): fecha y hora límite. Es opcional, pero **en las tareas que se repiten conviene ponerla siempre**: esa hora es la que se usa como límite en cada repetición (por ejemplo, "todos los días a las 23:59").
   - **Mantener si no completadas**, solo para las que se repiten (ver más abajo).
3. Tocá **Save Task** (Guardar tarea).

**Las tres pestañas**

- **Por hacer**: tareas pendientes.
- **Completadas**: tareas que ya terminaste.
- **No completadas**: tareas cuya fecha límite pasó sin que las completaras. Pasan acá cuando abrís o recargás la app (tecla F5).

**Completar una tarea**

1. En **Por hacer**, tocá el círculo a la izquierda de la tarea.
2. La tarea se pinta de verde y aparece una cuenta regresiva de unos 5 segundos.
3. Si te equivocaste, tocá el círculo otra vez antes de que termine y se cancela.
4. Cuando termina la cuenta, la tarea pasa a **Completadas**.

**Deshacer una tarea completada**

En **Completadas**, tocá la flecha circular al lado de la tarea. Vuelve a **Por hacer**, o a **No completadas** si su fecha ya pasó.

**Borrar tareas**

- **Una tarea:** tocá el tachito de basura a la derecha. Se borra en el momento, **sin pedir confirmación**.
- **Varias tareas completadas juntas:** en **Completadas**, marcá las casillas de las que quieras borrar (o **Seleccionar todo**) y tocá **Eliminar seleccionadas**. La app pide confirmación porque no se puede deshacer.

**Cómo funcionan las tareas que se repiten**

- La app crea la tarea del período actual (por ejemplo, la de hoy) y la del siguiente, así ves lo que viene.
- Al completar una, aparece la siguiente.
- Si una repetición vence sin completarse:
  - con **Mantener si no completadas** marcado, queda guardada en **No completadas** para que veas lo que te quedó pendiente;
  - sin esa opción, desaparece cuando termina su período, para no llenarte la lista.

### Finanzas (Finance)

Para registrar ingresos y gastos.

**Cargar un movimiento a mano**

1. Tocá **Add** (Agregar).
2. Completá:
   - **Amount** (Monto): el número, sin signo. Obligatorio.
   - **Type** (Tipo): **Expense** (gasto) o **Income** (ingreso).
   - **Category** (Categoría): por ejemplo "Supermercado" o "Sueldo". Obligatorio.
   - **Source** (Origen): de dónde salió la plata (por ejemplo "Efectivo" o el nombre del banco). Viene con "Manual".
   - **Date** (Fecha): viene con la fecha de hoy.
3. Tocá **Save Transaction** (Guardar movimiento).

El movimiento aparece en la tabla: los ingresos en verde con `+` y los gastos en rojo con `-`. La columna **Description** (Descripción) solo se completa en los movimientos importados del banco; en los cargados a mano muestra un guion. Para borrarlo, tocá el tachito de esa fila. **Los movimientos no se pueden editar**: si te equivocaste, borralo y cargalo de nuevo.

**Importar movimientos del banco**

1. Tocá **Import** (Importar).
2. En **Source** (Origen) elegí el banco: **BBVA**, **Banco Nación** o **MercadoPago**.
3. En el cuadro de texto, pegá los movimientos y tocá **Import Data**.

Por ahora solo se acepta este formato, **un movimiento por renglón**:

| Banco | Cómo tiene que verse cada renglón | Ejemplo |
|---|---|---|
| BBVA | fecha, descripción y monto, separados por **coma** | `2026-11-15,Supermercado,-2500.50` |
| Banco Nación | lo mismo, pero separados por **punto y coma** | `2026-11-15;Sueldo;350000` |
| MercadoPago | el formato técnico que entrega MercadoPago (llamado "JSON") | — |

- Un monto con **signo menos** se toma como gasto; sin signo, como ingreso.
- Usá **punto** para los decimales y no pongas separador de miles (`2500.50`, no `2.500,50`).
- La fecha conviene escribirla como año-mes-día (`2026-11-15`).
- Los renglones que no respetan el formato se ignoran sin aviso.
- Todo lo importado queda con categoría "Uncategorized" (sin categoría).

### Rutinas (Routines)

Para hábitos que querés cumplir todos los días.

**Crear una rutina**

1. Tocá **Add Routine** (Agregar rutina).
2. Completá **Title** (Título), **Start Time** (hora de inicio) y **End Time** (hora de fin).
3. Tocá **Save Routine** (Guardar rutina).

Por ahora todas las rutinas aplican a los 7 días de la semana (las etiquetas Sun, Mon, Tue… son domingo, lunes, martes…).

**Marcar cómo te fue hoy**

En cada rutina hay dos botones:

- **Done** (Hecho): la cumpliste hoy.
- **Missed** (No la hice): no la cumpliste hoy.

Si te equivocás, tocá el otro botón: vale el último que toques en el día. Todavía no se muestra en pantalla qué marcaste.

**Puntaje de disciplina (Discipline Score)**

Aparece arriba en Rutinas y en el Inicio. Es el porcentaje de tus rutinas que marcaste como **Done** hoy. Por ejemplo: si tenés 4 rutinas y marcaste 3 como hechas, el puntaje es 75%. Cada día empieza de cero.

Para borrar una rutina, tocá el tachito en su tarjeta.

---

## Para desarrolladores

### Tecnologías

- **Frontend:** React 18 + Vite, Tailwind CSS, Lucide (íconos), Recharts (gráficos).
- **Backend:** Node.js + Express (carpeta `api/`).
- **Base de datos:** MongoDB (con respaldo en memoria mediante `mongodb-memory-server`) o Supabase (Postgres).

### Instalación

1. Instalar [Node.js](https://nodejs.org).
2. En la carpeta del proyecto:
   ```bash
   npm install
   ```
3. Opcional (solo para Supabase o un MongoDB propio): copiar `.env.example` como `.env` y completar los valores.

### Scripts

| Comando | Qué hace |
|---|---|
| `npm run dev` | Frontend (Vite, puerto 5173) + backend (Express, puerto 3001) juntos. |
| `npm run client:dev` | Solo el frontend. Alcanza para los modos `local` y `supabase`. |
| `npm run server:dev` | Solo el backend. |
| `npm run build` / `npm run preview` | Compila y sirve la versión de producción del frontend. |
| `npm run check` / `npm run lint` | Chequeo de tipos y linter. |

### Proveedores de datos

El frontend elige de dónde leer los datos con el parámetro `?dataProvider=` en la URL (queda guardado en `localStorage`):

| Valor | Implementación | Notas |
|---|---|---|
| `local` | `src/services/localBackend.ts` | Todo en `localStorage`. No necesita backend. |
| `remote` (por defecto) | `src/services/remoteBackend.ts` → API Express | El backend usa `DB_PROVIDER=mongodb` o `supabase` (`api/repositories/`). Con MongoDB sin conexión y `NODE_ENV` distinto de `production`, usa una base en memoria que se pierde al cerrar. |
| `supabase` | `src/services/supabaseBackend.ts` | El cliente va directo a Supabase con `VITE_SUPABASE_URL` / `VITE_SUPABASE_ANON_KEY`. Las tablas y la política de seguridad por filas (RLS) están en `supabase_schema.sql`. La lógica de recurrencia, vencimiento automático e importación de resúmenes todavía no está implementada en este modo. |

La autenticación es simulada: el backend inyecta un usuario fijo (`api/middleware/mockAuth.ts`) y el modo Supabase usa un `user_id` fijo.

### Estructura

```
api/          Backend Express: rutas, controladores, servicios, repositorios (mongodb / supabase)
src/pages/    Pantallas: Dashboard, Tasks, Finance, Routines
src/context/  Estado compartido de cada sección
src/services/ Proveedores de datos del frontend (local / remote / supabase)
start_app.bat Lanzador con menú para Windows
vercel.json   Configuración de despliegue en Vercel
```
