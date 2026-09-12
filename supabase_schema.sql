-- =============================================================================
--  OrganizAPP — Supabase Schema (SQL)
-- =============================================================================
--  Reemplaza 1:1 las colecciones de MongoDB.
--  USO: Abrir SQL Editor en Supabase y pegar TODO este script.
--  Tablas: users, task_series, tasks, transactions, routines, routine_check_ins
--  Incluye: UUID por defecto, FKs, indices, Row Level Security (plantilla),
--           actualizadores automaticos de updated_at, y un seed inicial para
--           el DEFAULT_USER_ID del mockAuth ('60d0fe4f5311236168a109ca').
-- =============================================================================

-- -----------------------------------------------------------------------------
--  Extensiones
-- -----------------------------------------------------------------------------
create extension if not exists "pgcrypto"; -- gen_random_uuid()

-- -----------------------------------------------------------------------------
--  Helpers: auto-actualiza updated_at en cada modify
-- -----------------------------------------------------------------------------
create or replace function public.set_current_timestamp_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end $$;

-- -----------------------------------------------------------------------------
--  1. users
-- -----------------------------------------------------------------------------
create table if not exists public.users (
  id              uuid primary key default gen_random_uuid(),
  name            text not null,
  email           text not null unique,
  password_hash   text,
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);

create trigger users_set_updated_at
before update on public.users
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  2. task_series
-- -----------------------------------------------------------------------------
create table if not exists public.task_series (
  id                             uuid primary key default gen_random_uuid(),
  user_id                        uuid not null references public.users(id) on delete cascade,
  title                          text not null,
  description                    text,
  recurrence                     text not null check (recurrence in ('daily','weekly','monthly','yearly')),
  priority                       text not null default 'medium' check (priority in ('low','medium','high')),
  keep_if_missed                 boolean not null default false,
  due_rule                       jsonb not null,
  start_at                       timestamptz not null,
  stop_at                        timestamptz,
  last_generated_interval_start  timestamptz,
  legacy_series_id               text,
  created_at                     timestamptz not null default now(),
  updated_at                     timestamptz not null default now()
);

create index if not exists idx_task_series_user_id            on public.task_series(user_id);
create index if not exists idx_task_series_user_recurrence    on public.task_series(user_id, recurrence);
create index if not exists idx_task_series_legacy_series_id   on public.task_series(legacy_series_id);

create trigger task_series_set_updated_at
before update on public.task_series
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  3. tasks
-- -----------------------------------------------------------------------------
create table if not exists public.tasks (
  id               uuid primary key default gen_random_uuid(),
  user_id          uuid not null references public.users(id) on delete cascade,
  series_id        uuid references public.task_series(id) on delete set null,
  series_id_legacy text,
  title            text not null,
  description      text,
  type             text not null default 'one-time' check (type in ('one-time','recurring')),
  recurrence       text check (recurrence in ('daily','weekly','monthly','yearly')),
  status           text not null default 'pending' check (status in ('pending','completed','missed')),
  priority         text not null default 'medium' check (priority in ('low','medium','high')),
  due_date         timestamptz,
  interval_start   timestamptz,
  interval_end     timestamptz,
  keep_if_missed   boolean default false,
  completed_at     timestamptz,
  missed_at        timestamptz,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now()
);

create index if not exists idx_tasks_user_id              on public.tasks(user_id);
create index if not exists idx_tasks_user_due_date        on public.tasks(user_id, due_date);
create index if not exists idx_tasks_user_series_id       on public.tasks(user_id, series_id);
create index if not exists idx_tasks_series_id_legacy     on public.tasks(series_id_legacy);
create index if not exists idx_tasks_interval_end         on public.tasks(interval_end);

create trigger tasks_set_updated_at
before update on public.tasks
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  4. transactions
-- -----------------------------------------------------------------------------
create table if not exists public.transactions (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  amount      numeric(12,2) not null,
  date        timestamptz not null,
  category    text not null,
  source_bank text not null,
  type        text not null check (type in ('income','expense')),
  description text,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create index if not exists idx_transactions_user_date   on public.transactions(user_id, date desc);
create index if not exists idx_transactions_user_type   on public.transactions(user_id, type);

create trigger transactions_set_updated_at
before update on public.transactions
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  5. routines
-- -----------------------------------------------------------------------------
create table if not exists public.routines (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  title       text not null,
  start_time  text not null,
  end_time    text not null,
  days        integer[] not null default '{}'::integer[],
  active      boolean not null default true,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create index if not exists idx_routines_user_id     on public.routines(user_id);
create index if not exists idx_routines_user_active on public.routines(user_id, active);

create trigger routines_set_updated_at
before update on public.routines
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  6. routine_check_ins
-- -----------------------------------------------------------------------------
create table if not exists public.routine_check_ins (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  routine_id  uuid not null references public.routines(id) on delete cascade,
  date        text not null, -- YYYY-MM-DD
  status      text not null default 'completed' check (status in ('completed','missed')),
  completed_at timestamptz,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),
  unique (user_id, routine_id, date)
);

create index if not exists idx_routine_check_ins_user_date  on public.routine_check_ins(user_id, date);
create index if not exists idx_routine_check_ins_routine    on public.routine_check_ins(routine_id, date);

create trigger routine_check_ins_set_updated_at
before update on public.routine_check_ins
for each row execute function public.set_current_timestamp_updated_at();

-- -----------------------------------------------------------------------------
--  Row Level Security (RLS) — plantilla
--  Descomenta los bloques cuando integres Supabase Auth de verdad.
--  Por defecto estan comentados para que funcione el mockAuth del backend.
-- -----------------------------------------------------------------------------
/*
alter table public.users               enable row level security;
alter table public.task_series         enable row level security;
alter table public.tasks               enable row level security;
alter table public.transactions        enable row level security;
alter table public.routines            enable row level security;
alter table public.routine_check_ins   enable row level security;

create policy "User can see own row"           on public.users               for select using (auth.uid() = id);
create policy "User manages own series"        on public.task_series         for all    using (auth.uid() = user_id)   with check (auth.uid() = user_id);
create policy "User manages own tasks"         on public.tasks               for all    using (auth.uid() = user_id)   with check (auth.uid() = user_id);
create policy "User manages own transactions"  on public.transactions        for all    using (auth.uid() = user_id)   with check (auth.uid() = user_id);
create policy "User manages own routines"      on public.routines            for all    using (auth.uid() = user_id)   with check (auth.uid() = user_id);
create policy "User manages own check-ins"     on public.routine_check_ins   for all    using (auth.uid() = user_id)   with check (auth.uid() = user_id);
*/

-- -----------------------------------------------------------------------------
--  Seed: usuario DEFAULT_USER_ID usado por mockAuth en el backend Express.
--        (UUID generado a partir del hex original para mantener compatibilidad)
--  DEFAULT_USER_ID = '60d0fe4f5311236168a109ca'
-- -----------------------------------------------------------------------------
insert into public.users (id, name, email, password_hash)
values (
  '60d0fe4f-5311-4361-68a1-09ca00000001'::uuid,
  'Usuario Demo',
  'demo@organizapp.local',
  null
)
on conflict (email) do nothing;

-- -----------------------------------------------------------------------------
--  Informacion post-ejecucion
-- -----------------------------------------------------------------------------
--  Luego de ejecutar, en la app setea en el .env:
--    DB_PROVIDER=supabase
--    SUPABASE_URL=https://TU-PROYECTO.supabase.co
--    SUPABASE_SERVICE_ROLE_KEY=...
--  Si vas a usar Supabase Directo en frontend:
--    VITE_SUPABASE_URL=https://TU-PROYECTO.supabase.co
--    VITE_SUPABASE_ANON_KEY=...
