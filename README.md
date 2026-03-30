# SmartMind Graphic Layer Plugin

**[Español](#español) | [English](#english)**

---

# Español

Plugin local de Moodle que proporciona una capa grafica personalizada para la plataforma de aprendizaje SmartMind, junto con el tema SmartMind.

## Arquitectura

El plugin tiene dos capas:

- **Backend (PHP)** — API de matriculacion, funciones externas (AJAX), hooks, observers, base de datos. Vive en la raiz del repo.
- **Frontend (Vue/Nuxt)** — SPA en `frontend/` construida con **Nuxt 4 + Vue 3 + TypeScript + Bootstrap 5**. Reemplaza completamente los templates Mustache. El equipo programa las paginas en Vue/TypeScript.

El frontend se sirve desde Moodle a traves de `pages/spa.php`, que inyecta datos del usuario (sesskey, rol, empresa) y carga la SPA. Las llamadas a la API usan el mecanismo AJAX nativo de Moodle (`/lib/ajax/service.php`) con la cookie de sesion — no se necesitan tokens.

### Componentes

- **`local_sm_graphics_plugin`** — Plugin local: funciones externas, hooks, observers, base de datos
- **`theme_smartmind`** — Tema hijo de Boost: layouts para paginas Moodle nativas ([submodulo git](https://github.com/SmartmindTech/SM_Theme_Moodle))
- **`frontend/`** — SPA Nuxt 4: todas las paginas del usuario (cursos, dashboard, gestion, admin)

## Estructura del proyecto

```
SM_Aprendizaje_Ilimitado_Plugin/
├── version.php, lib.php, settings.php    Archivos principales del plugin (PHP)
├── db/                                   Install, upgrade, hooks, events, services.php
├── classes/external/                     ~40 funciones externas (API JSON via AJAX)
├── classes/output/                       Renderers (usados por las funciones externas)
├── pages/spa.php                         Punto de entrada unico para la SPA
├── pages/*.php                           Paginas legacy (redirigen a la SPA)
├── lang/                                 Cadenas de idioma (en, es, pt_br)
│
├── frontend/                             *** SPA Nuxt (donde se programa el frontend) ***
│   ├── nuxt.config.ts                    Configuracion Nuxt (SPA mode, proxy, SCSS)
│   ├── package.json                      Dependencias Node.js
│   ├── app/
│   │   ├── pages/                        Paginas Vue (rutas automaticas)
│   │   ├── composables/api_calls/        Composables para llamadas AJAX a Moodle
│   │   ├── stores/                       Pinia stores (patron Setup API)
│   │   ├── components/                   Componentes Vue reutilizables
│   │   ├── types/                        Interfaces TypeScript
│   │   ├── layouts/                      Layouts (default, admin, minimal)
│   │   ├── middleware/                   Guards de ruta (auth)
│   │   └── assets/scss/                  SCSS (Bootstrap 5, variables SmartMind)
│   └── i18n/                             Traducciones (es, en, pt_br)
│
├── frontend_dist/                        Build de la SPA (generado, no editar)
├── theme_smartmind/                      Tema (submodulo git)
└── scripts/                              Scripts de desarrollo y deploy
```

## Instalacion

### Desarrollo

#### Paso 1 — Clonar

```bash
git clone --recurse-submodules https://github.com/SmartmindTech/SM_Moodle_Graphic_Layer_Plugin.git
cd SM_Moodle_Graphic_Layer_Plugin
cd theme_smartmind && git checkout dev && cd ..
```

#### Paso 2 — Instalar dependencias del frontend

```bash
cd frontend
npm install
cd ..
```

#### Paso 3 — Configurar

```bash
cp .env.example .env                    # Moodle path
cp frontend/.env.example frontend/.env  # Puerto de Moodle Docker
```

Edita `frontend/.env`:
```
MOODLE_URL=http://localhost:8081   # Paulo usa 8081, otros usan 8080
```

#### Paso 4 — Ejecutar setup

```bash
./scripts/docker_linux/setup.sh
```

## Flujo de desarrollo del frontend

### Desarrollo con Hot Reload (HMR)

```bash
# Terminal 1: Moodle en Docker (ya deberia estar corriendo)

# Terminal 2: Servidor de desarrollo Nuxt
cd frontend
npm run dev
# Abre http://localhost:3000 en el navegador
# Primero inicia sesion en Moodle (http://localhost:8081) para obtener la cookie de sesion
```

El proxy de Vite redirige las llamadas AJAX a tu Moodle Docker automaticamente. Los cambios en archivos `.vue` y `.ts` se reflejan al instante en el navegador.

### Build para produccion

```bash
cd frontend
npm run deploy     # Genera la SPA y copia a frontend_dist/
```

Luego despliega el plugin a Moodle:

```bash
sudo docker cp . iomad_app:/var/www/html/local/sm_graphics_plugin/ && \
sudo docker exec iomad_app php /var/www/html/admin/cli/upgrade.php --non-interactive && \
sudo docker exec iomad_app php /var/www/html/admin/cli/purge_caches.php
```

### Donde programar cada cosa

| Quiero cambiar... | Editar en... | Lenguaje |
|---|---|---|
| Una pagina del usuario (UI, layout, interacciones) | `frontend/app/pages/*.vue` | Vue + TypeScript |
| Un componente reutilizable (card, tabla, modal) | `frontend/app/components/*.vue` | Vue + TypeScript |
| Estilos (colores, spacing, responsive) | `frontend/app/assets/scss/` | SCSS |
| Llamadas a la API de Moodle | `frontend/app/composables/api_calls/` | TypeScript |
| Estado global (datos del curso, usuario) | `frontend/app/stores/` | TypeScript |
| Traducciones | `frontend/i18n/locales/` | JSON |
| Una funcion de la API (nueva query, nueva accion) | `classes/external/*.php` + `db/services.php` | PHP |
| Base de datos (nueva tabla, nueva columna) | `db/install.xml` + `db/upgrade.php` | PHP/XML |

### Crear una nueva pagina

1. Crea `frontend/app/pages/mi-pagina.vue` — la ruta se genera automaticamente como `/mi-pagina`
2. Si necesitas datos de Moodle, crea una funcion externa en `classes/external/get_mi_data.php`
3. Registrala en `db/services.php`
4. Llama a la funcion desde el composable con `useMoodleAjax()`:

```vue
<script setup lang="ts">
const { call } = useMoodleAjax()

const { data } = await call('local_sm_graphics_plugin_get_mi_data', { param: 123 })
</script>
```

### Crear una nueva funcion externa (API)

1. Crea `classes/external/mi_funcion.php`:

```php
namespace local_sm_graphics_plugin\external;

class mi_funcion extends \external_api {
    public static function execute_parameters() { ... }
    public static function execute(): array { ... }
    public static function execute_returns() { ... }
}
```

2. Registra en `db/services.php`:

```php
'local_sm_graphics_plugin_mi_funcion' => [
    'classname'   => 'local_sm_graphics_plugin\external\mi_funcion',
    'methodname'  => 'execute',
    'type'        => 'read',  // o 'write'
    'ajax'        => true,
],
```

3. Bump `version.php` y ejecuta upgrade.

## Paginas Vue existentes

| Ruta | Archivo | Descripcion |
|---|---|---|
| `/` | `pages/index.vue` | Bienvenida |
| `/dashboard` | `pages/dashboard.vue` | Espacio personal (reemplaza /my/) |
| `/catalogue` | `pages/catalogue.vue` | Catalogo de cursos |
| `/courses` | `pages/courses/index.vue` | Mis cursos |
| `/courses/:id/landing` | `pages/courses/[id]/landing.vue` | Landing del curso |
| `/courses/:id/player` | `pages/courses/[id]/player.vue` | Reproductor del curso |
| `/grades-certificates` | `pages/grades-certificates.vue` | Notas y certificados |
| `/management/users` | `pages/management/users.vue` | Gestion de usuarios |
| `/management/courses` | `pages/management/courses.vue` | Gestion de cursos |
| `/management/categories` | `pages/management/categories.vue` | Gestion de categorias |
| `/statistics` | `pages/statistics.vue` | Estadisticas |
| `/admin/settings` | `pages/admin/settings.vue` | Configuracion del plugin |
| `/admin/company-limits` | `pages/admin/company-limits.vue` | Limites de empresa |
| `/admin/iomad-dashboard` | `pages/admin/iomad-dashboard.vue` | Dashboard IOMAD |
| `/admin/updates` | `pages/admin/updates.vue` | Actualizaciones |

## Git

```bash
git checkout devPaulo
git status
git add .
git commit -m "Describe los cambios realizados"
git push origin devPaulo
git checkout dev
git pull origin dev
git merge devPaulo
git push origin dev
git checkout devPaulo
```

## Requisitos

- Moodle 5.0+, PHP 8.2+, theme_boost
- Node.js 22+, npm
- Docker (para desarrollo con iomad_app)

---

# English

A Moodle local plugin that provides a custom graphic layer for SmartMind's learning platform, bundled with the SmartMind theme.

## Architecture

The plugin has two layers:

- **Backend (PHP)** — Enrollment API, external functions (AJAX), hooks, observers, database. Lives at the repo root.
- **Frontend (Vue/Nuxt)** — SPA in `frontend/` built with **Nuxt 4 + Vue 3 + TypeScript + Bootstrap 5**. Completely replaces Mustache templates. The team codes all pages in Vue/TypeScript.

The frontend is served from Moodle via `pages/spa.php`, which injects user data (sesskey, role, company) and loads the SPA. API calls use Moodle's native AJAX mechanism (`/lib/ajax/service.php`) with the session cookie — no tokens needed.

### Components

- **`local_sm_graphics_plugin`** — Local plugin: external functions, hooks, observers, database
- **`theme_smartmind`** — Boost child theme: layouts for native Moodle pages ([git submodule](https://github.com/SmartmindTech/SM_Theme_Moodle))
- **`frontend/`** — Nuxt 4 SPA: all user-facing pages (courses, dashboard, management, admin)

## Project Structure

```
SM_Aprendizaje_Ilimitado_Plugin/
├── version.php, lib.php, settings.php    Plugin core files (PHP)
├── db/                                   Install, upgrade, hooks, events, services.php
├── classes/external/                     ~40 external functions (JSON API via AJAX)
├── classes/output/                       Renderers (used by external functions)
├── pages/spa.php                         Single entry point for the SPA
├── pages/*.php                           Legacy pages (redirect to SPA)
├── lang/                                 Language strings (en, es, pt_br)
│
├── frontend/                             *** Nuxt SPA (where frontend code lives) ***
│   ├── nuxt.config.ts                    Nuxt config (SPA mode, proxy, SCSS)
│   ├── package.json                      Node.js dependencies
│   ├── app/
│   │   ├── pages/                        Vue pages (auto-routed)
│   │   ├── composables/api_calls/        Composables for Moodle AJAX calls
│   │   ├── stores/                       Pinia stores (Setup API pattern)
│   │   ├── components/                   Reusable Vue components
│   │   ├── types/                        TypeScript interfaces
│   │   ├── layouts/                      Layouts (default, admin, minimal)
│   │   ├── middleware/                   Route guards (auth)
│   │   └── assets/scss/                  SCSS (Bootstrap 5, SmartMind vars)
│   └── i18n/                             Translations (es, en, pt_br)
│
├── frontend_dist/                        SPA build output (generated, do not edit)
├── theme_smartmind/                      Theme (git submodule)
└── scripts/                              Dev and deploy scripts
```

## Installation

### Development

#### Step 1 — Clone

```bash
git clone --recurse-submodules https://github.com/SmartmindTech/SM_Moodle_Graphic_Layer_Plugin.git
cd SM_Moodle_Graphic_Layer_Plugin
cd theme_smartmind && git checkout dev && cd ..
```

#### Step 2 — Install frontend dependencies

```bash
cd frontend
npm install
cd ..
```

#### Step 3 — Configure

```bash
cp .env.example .env                    # Moodle path
cp frontend/.env.example frontend/.env  # Moodle Docker port
```

Edit `frontend/.env`:
```
MOODLE_URL=http://localhost:8081   # Paulo uses 8081, others use 8080
```

#### Step 4 — Run setup

```bash
./scripts/docker_linux/setup.sh
```

## Frontend Development Workflow

### Development with Hot Reload (HMR)

```bash
# Terminal 1: Moodle running in Docker (should already be running)

# Terminal 2: Nuxt dev server
cd frontend
npm run dev
# Open http://localhost:3000 in browser
# First log into Moodle (http://localhost:8081) to get the session cookie
```

Vite's proxy forwards AJAX calls to your Moodle Docker automatically. Changes to `.vue` and `.ts` files are reflected instantly in the browser.

### Production build

```bash
cd frontend
npm run deploy     # Generates the SPA and copies to frontend_dist/
```

Then deploy the plugin to Moodle:

```bash
sudo docker cp . iomad_app:/var/www/html/local/sm_graphics_plugin/ && \
sudo docker exec iomad_app php /var/www/html/admin/cli/upgrade.php --non-interactive && \
sudo docker exec iomad_app php /var/www/html/admin/cli/purge_caches.php
```

### Where to code what

| I want to change... | Edit in... | Language |
|---|---|---|
| A user page (UI, layout, interactions) | `frontend/app/pages/*.vue` | Vue + TypeScript |
| A reusable component (card, table, modal) | `frontend/app/components/*.vue` | Vue + TypeScript |
| Styles (colors, spacing, responsive) | `frontend/app/assets/scss/` | SCSS |
| Moodle API calls | `frontend/app/composables/api_calls/` | TypeScript |
| Global state (course data, user) | `frontend/app/stores/` | TypeScript |
| Translations | `frontend/i18n/locales/` | JSON |
| An API function (new query, new action) | `classes/external/*.php` + `db/services.php` | PHP |
| Database (new table, new column) | `db/install.xml` + `db/upgrade.php` | PHP/XML |

### Creating a new page

1. Create `frontend/app/pages/my-page.vue` — route auto-generates as `/my-page`
2. If you need Moodle data, create an external function in `classes/external/get_my_data.php`
3. Register it in `db/services.php`
4. Call the function from a composable with `useMoodleAjax()`:

```vue
<script setup lang="ts">
const { call } = useMoodleAjax()

const { data } = await call('local_sm_graphics_plugin_get_my_data', { param: 123 })
</script>
```

### Creating a new external function (API)

1. Create `classes/external/my_function.php`:

```php
namespace local_sm_graphics_plugin\external;

class my_function extends \external_api {
    public static function execute_parameters() { ... }
    public static function execute(): array { ... }
    public static function execute_returns() { ... }
}
```

2. Register in `db/services.php`:

```php
'local_sm_graphics_plugin_my_function' => [
    'classname'   => 'local_sm_graphics_plugin\external\my_function',
    'methodname'  => 'execute',
    'type'        => 'read',  // or 'write'
    'ajax'        => true,
],
```

3. Bump `version.php` and run upgrade.

## Existing Vue Pages

| Route | File | Description |
|---|---|---|
| `/` | `pages/index.vue` | Welcome |
| `/dashboard` | `pages/dashboard.vue` | Personal space (replaces /my/) |
| `/catalogue` | `pages/catalogue.vue` | Course catalogue |
| `/courses` | `pages/courses/index.vue` | My courses |
| `/courses/:id/landing` | `pages/courses/[id]/landing.vue` | Course landing |
| `/courses/:id/player` | `pages/courses/[id]/player.vue` | Course player |
| `/grades-certificates` | `pages/grades-certificates.vue` | Grades & certificates |
| `/management/users` | `pages/management/users.vue` | User management |
| `/management/courses` | `pages/management/courses.vue` | Course management |
| `/management/categories` | `pages/management/categories.vue` | Category management |
| `/statistics` | `pages/statistics.vue` | Statistics |
| `/admin/settings` | `pages/admin/settings.vue` | Plugin settings |
| `/admin/company-limits` | `pages/admin/company-limits.vue` | Company limits |
| `/admin/iomad-dashboard` | `pages/admin/iomad-dashboard.vue` | IOMAD dashboard |
| `/admin/updates` | `pages/admin/updates.vue` | Plugin updates |

## Git

```bash
git checkout devPaulo
git status
git add .
git commit -m "Describe your changes"
git push origin devPaulo
git checkout dev
git pull origin dev
git merge devPaulo
git push origin dev
git checkout devPaulo
```

## Requirements

- Moodle 5.0+, PHP 8.2+, theme_boost
- Node.js 22+, npm
- Docker (for development with iomad_app)
