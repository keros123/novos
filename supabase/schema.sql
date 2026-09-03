-- Ejecutar en Supabase → SQL Editor

create extension if not exists "pgcrypto";

create table if not exists public.productos (
  id uuid primary key default gen_random_uuid(),
  nombre text not null,
  descripcion text default '',
  categoria text not null default 'oficina'
    check (categoria in ('oficina', 'hogar', 'tecnologia', 'papeleria', 'otro')),
  precio numeric(12,2) not null default 0 check (precio >= 0),
  stock integer not null default 0 check (stock >= 0),
  fecha_ingreso date not null default current_date,
  activo boolean not null default true,
  email_contacto text,
  imagen_url text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

alter table public.productos enable row level security;

-- El backend PHP usa la service_role (omite RLS).
-- No hay políticas públicas: el anon key no puede leer ni escribir.

insert into storage.buckets (id, name, public)
values ('productos', 'productos', true)
on conflict (id) do update set public = true;

drop policy if exists "Lectura pública de imágenes" on storage.objects;

create policy "Lectura pública de imágenes"
on storage.objects for select
using (bucket_id = 'productos');
