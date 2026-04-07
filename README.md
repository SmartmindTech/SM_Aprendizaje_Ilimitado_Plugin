# SmartMind Aprendizaje Ilimitado Plugin

**[Español](#español) | [English](#english)**

A Moodle local plugin that provides a custom graphic layer for SmartMind's learning platform, with a Vue 3 + Nuxt 4 SPA frontend and a bundled Boost child theme.

---

# Español

Plugin local de Moodle (`local_sm_graphics_plugin`) que reemplaza completamente la UI nativa por una SPA Vue/Nuxt y empaqueta el tema `theme_smartmind`.

## Arquitectura

- **Backend (PHP)** — Funciones externas (AJAX), hooks, observers, base de datos. Vive en la raíz del repo.
- **Frontend (Vue/Nuxt)** — SPA en `frontend/` (Nuxt 4 + Vue 3 + TypeScript + Bootstrap 5). Reemplaza completamente las páginas nativas y los templates Mustache.
- **Tema (`theme_smartmind/`)** — Tema hijo de Boost empaquetado dentro del plugin. Se despliega a `/theme/smartmind/` automáticamente al instalar/actualizar.

La SPA se sirve desde `pages/spa.php`, que inyecta los datos del usuario (sesskey, rol, empresa, idioma) en `window.__MOODLE_BOOTSTRAP__`. Las llamadas a la API usan el mecanismo AJAX nativo de Moodle (`/lib/ajax/service.php`) con la cookie de sesión — sin tokens ni CORS.

### Estructura del proyecto

```
SM_Aprendizaje_Ilimitado_Plugin/
├── version.php, lib.php, settings.php    Núcleo del plugin (PHP)
├── db/                                   Install, upgrade, hooks, events, services.php
├── classes/external/                     ~50 funciones externas (API JSON vía AJAX)
├── classes/output/                       Renderers (consumidos por las funciones externas)
├── classes/sharepoint/                   Cliente Microsoft Graph para imports SP
├── classes/task/                         Tareas programadas (sync SP, check updates)
├── pages/spa.php                         Punto de entrada único de la SPA
├── pages/courseloader_sync.php           Endpoint SSE para progreso de import SP
├── pages/{download,verify}_certificate.php  Endpoints públicos de certificados
├── update.php, classes/update_checker.php   Auto-actualización en la UI desde GitHub
├── lang/{en,es,pt_br}/                   Cadenas de idioma
│
├── frontend/                             *** SPA Nuxt (donde vive el código frontend) ***
│   ├── nuxt.config.ts                    Configuración Nuxt (SPA, HMR, proxy, SCSS)
│   ├── package.json
│   └── app/
│       ├── pages/                        Páginas Vue (rutas auto-generadas, hash routing)
│       ├── components/                   Componentes reutilizables
│       ├── composables/api_calls/        Composables para llamadas AJAX
│       ├── stores/                       Pinia stores
│       ├── layouts/                      Layouts (default, admin, minimal)
│       ├── middleware/                   Guards de ruta (auth)
│       └── assets/scss/                  SCSS (Bootstrap 5 + variables SmartMind)
│   └── i18n/locales/                     Traducciones (es.json, en.json, pt_br.json)
│
├── theme_smartmind/                      Tema Boost-child empaquetado
├── frontend_dist/                        Build de la SPA (gitignored, generado por CI)
├── .github/workflows/release.yml         CI: build + release automático en push
└── scripts/docker_{linux,windows}/       Scripts de desarrollo
```

## Instalación

### Para usar (usuario final)

1. Descarga el ZIP de la última release: <https://github.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin/releases/latest>
2. En Moodle: Site admin → Plugins → Install plugins → sube el ZIP
3. Sigue el flujo de upgrade. El plugin auto-despliega el tema y verifica que `frontend_dist/` esté presente.

> ⚠️ **No clones el repo directamente en `local/sm_graphics_plugin/`** — `frontend_dist/` está en `.gitignore` y CI lo construye en cada release. El instalador detecta esto y muestra un error claro si falta.

### Para desarrollo

#### 1. Clonar

```bash
git clone https://github.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin.git
cd SM_Aprendizaje_Ilimitado_Plugin
```

#### 2. Configurar `.env`

```bash
cp .env.example .env
```

Edita las variables relevantes:
```bash
MOODLE_PATH=/ruta/a/Moodle      # Solo para scripts/linux/ no-Docker
DOCKER_CONTAINER=iomad_app       # Nombre del contenedor Docker
GIT_BRANCH=devPaulo              # Tu rama personal
UPDATE_BRANCH=latest             # Canal de actualizaciones (latest = release asset)
SPA_DEV=1                        # Habilita HMR vía Nuxt dev server
SPA_DEV_PORT=4173                # Puerto del Nuxt dev server
GEMINI_API_KEY=...               # Para auto-traducción de objetivos
SMTP_*, AZURE_*                  # Si usas SharePoint y email
```

#### 3. Instalar dependencias del frontend

```bash
cd frontend && npm install && cd ..
```

#### 4. Arrancar el watcher (un solo comando)

```bash
./scripts/docker_linux/watch.sh         # Linux/WSL
.\scripts\docker_windows\watch.ps1      # Windows
```

Esto hace tres cosas:
- Lanza `npm run dev` (Nuxt dev server) en background → HMR instantáneo de Vue/SCSS/i18n
- Vigila cambios en PHP/lang/db/classes/theme y los sincroniza al contenedor con `sync-theme.sh`
- Limpia el dev server al salir (Ctrl+C)

Logs del Nuxt dev server: `scripts/docker_linux/.nuxt-dev.log`.

#### 5. Abrir Moodle

Visita siempre la URL de Moodle (no la del Nuxt dev server):

```
http://localhost:8081/local/sm_graphics_plugin/pages/spa.php
```

`spa.php` detecta el dev server (vía `SPA_DEV=1` y `SPA_DEV_PORT`), proxea su HTML, e inyecta el bootstrap. La cookie de sesión funciona porque ambos puertos comparten el dominio `localhost`.

Cualquier edición en `frontend/app/**`, `frontend/i18n/**`, `frontend/assets/**` → recarga HMR en <300 ms sin tocar nada más.

### Workflow alternativo sin HMR (más lento)

Si no quieres ejecutar el dev server, pon `SPA_DEV=0` en `.env` y reconstruye manualmente cuando edites Vue:

```bash
cd frontend && npm run deploy && cd ..
./scripts/docker_linux/sync-theme.sh
```

`spa.php` servirá el `frontend_dist/` estático.

## Dónde editar cada cosa

| Quiero cambiar... | Editar en... |
|---|---|
| Una página de usuario | `frontend/app/pages/*.vue` |
| Un componente reutilizable | `frontend/app/components/*.vue` |
| Estilos | `frontend/app/assets/scss/` |
| Llamadas a la API de Moodle | `frontend/app/composables/api_calls/` |
| Estado global | `frontend/app/stores/` |
| Traducciones del frontend | `frontend/i18n/locales/{es,en,pt_br}.json` |
| Cadenas de idioma del backend | `lang/{es,en,pt_br}/local_sm_graphics_plugin.php` |
| Una función externa (API) | `classes/external/*.php` + `db/services.php` |
| Schema de BD | `db/install.xml` + `db/upgrade.php` (con savepoint) |
| Hooks del formulario de curso | `classes/hook/course_form_handler.php` |
| Observers de eventos | `classes/observer.php` + `db/events.php` |
| Tareas programadas | `classes/task/*.php` + `db/tasks.php` |
| Tema (layouts, SCSS de páginas core) | `theme_smartmind/` |

## Crear una nueva página Vue

1. Crea `frontend/app/pages/mi-pagina.vue` — la ruta es `#/mi-pagina` automáticamente.
2. Si necesitas datos de Moodle, crea una función externa en `classes/external/get_mi_data.php` y regístrala en `db/services.php`.
3. Llámala desde el composable:
   ```vue
   <script setup lang="ts">
   const { call } = useMoodleAjax()
   const result = await call('local_sm_graphics_plugin_get_mi_data', { param: 123 })
   </script>
   ```
4. Bump `version.php` (formato `YYYYMMDDXX`) si añadiste backend o BD.

## Releases y actualizaciones

### Release automático (CI)

Cualquier `push` a `main`, `dev` o `testnuxt` dispara `.github/workflows/release.yml` que:

1. Hace `npm ci && npm run deploy` para construir la SPA
2. Empaqueta el plugin en un ZIP (sin `frontend/`, sin `node_modules/`, sin `frontend/.nuxt/`, sin `frontend/.output/`)
3. Genera `update.xml` con la versión actual
4. Crea un GitHub release con el ZIP y `update.xml` como assets
5. Marca el release como `--latest`

No hay que ejecutar ningún build a mano para publicar — solo bumpear `version.php` y empujar.

### Actualización en la UI

Los Moodle instalados ven las nuevas releases automáticamente:

- **Site admin → Notifications → "Check for available updates"** detecta el release vía `https://github.com/.../releases/latest/download/update.xml` (caché ~1 minuto del CDN de GitHub Releases)
- En el plugin settings hay un botón **"Install update"** que descarga el ZIP, lo extrae y aplica los archivos
- Si la versión instalada coincide con la última release pero los archivos están desactualizados (por un sync parcial), aparece un botón **"Force reinstall latest"** que re-aplica el release

### Selección de canal vía `UPDATE_BRANCH`

| Valor | Comportamiento |
|---|---|
| `latest` (por defecto) | `releases/latest/download/update.xml` — auto-resuelve al release más reciente, ~1 min de caché |
| `main` / `dev` / `testnuxt` / `devPaulo` / ... | `raw.githubusercontent.com/<branch>/update.xml` — útil para fijar un Moodle a una rama concreta en desarrollo, ~5 min de caché |

## Git

Ramas: `main` (release), `dev` (integración), `testnuxt` (rama de la migración Vue), `devPaulo`/`devDiego`/`devAntonio` (personales).

```bash
git checkout devPaulo
git pull --rebase origin testnuxt   # o dev/main según donde estés trabajando
# ... cambios ...
git add .
git commit -m "Descripción breve"
git push origin devPaulo
```

Para integrar a `testnuxt`:
```bash
git checkout testnuxt
git pull --rebase
git merge devPaulo
git push origin testnuxt
```

`dump.html`, `image*.png`, `.env`, `frontend_dist/`, `frontend/node_modules/`, `frontend/.nuxt/`, `frontend/.output/` y `CLAUDE.md` están en `.gitignore` — no se suben nunca.

## Requisitos

- Moodle 5.0+, PHP 8.2+, IOMAD (opcional pero recomendado)
- Node.js 20+, npm
- Docker Desktop / WSL2 con `host.docker.internal` (para que `spa.php` alcance el Nuxt dev server desde el contenedor)

---

# English

Moodle local plugin (`local_sm_graphics_plugin`) that completely replaces the native UI with a Vue/Nuxt SPA, bundling the `theme_smartmind` Boost child theme.

## Architecture

- **Backend (PHP)** — External functions (AJAX), hooks, observers, database. Lives at the repo root.
- **Frontend (Vue/Nuxt)** — SPA in `frontend/` (Nuxt 4 + Vue 3 + TypeScript + Bootstrap 5). Completely replaces the native pages and the Mustache templates.
- **Theme (`theme_smartmind/`)** — Boost child theme bundled inside the plugin. Auto-deployed to `/theme/smartmind/` on install/upgrade.

The SPA is served by `pages/spa.php`, which injects user data (sesskey, role, company, language) into `window.__MOODLE_BOOTSTRAP__`. API calls use Moodle's native AJAX mechanism (`/lib/ajax/service.php`) with the session cookie — no tokens, no CORS.

### Project layout

```
SM_Aprendizaje_Ilimitado_Plugin/
├── version.php, lib.php, settings.php    Plugin core (PHP)
├── db/                                   Install, upgrade, hooks, events, services.php
├── classes/external/                     ~50 external functions (JSON API via AJAX)
├── classes/output/                       Renderers (consumed by external functions)
├── classes/sharepoint/                   Microsoft Graph client for SharePoint imports
├── classes/task/                         Scheduled tasks (SP sync, update check)
├── pages/spa.php                         Single SPA entry point
├── pages/courseloader_sync.php           SSE endpoint for SP import progress
├── pages/{download,verify}_certificate.php  Public certificate endpoints
├── update.php, classes/update_checker.php   In-UI auto-updater fetching from GitHub
├── lang/{en,es,pt_br}/                   Language strings
│
├── frontend/                             *** Nuxt SPA (where the frontend lives) ***
│   ├── nuxt.config.ts                    Nuxt config (SPA, HMR, proxy, SCSS)
│   ├── package.json
│   └── app/
│       ├── pages/                        Vue pages (auto-routed, hash routing)
│       ├── components/                   Reusable components
│       ├── composables/api_calls/        Composables for AJAX calls
│       ├── stores/                       Pinia stores
│       ├── layouts/                      Layouts (default, admin, minimal)
│       ├── middleware/                   Route guards (auth)
│       └── assets/scss/                  SCSS (Bootstrap 5 + SmartMind variables)
│   └── i18n/locales/                     Translations (es.json, en.json, pt_br.json)
│
├── theme_smartmind/                      Bundled Boost child theme
├── frontend_dist/                        SPA build output (gitignored, built by CI)
├── .github/workflows/release.yml         CI: auto build + release on push
└── scripts/docker_{linux,windows}/       Dev scripts
```

## Installation

### End user

1. Download the latest release ZIP: <https://github.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin/releases/latest>
2. In Moodle: Site admin → Plugins → Install plugins → upload the ZIP
3. Follow the upgrade flow. The plugin auto-deploys the bundled theme and verifies `frontend_dist/` is present.

> ⚠️ **Do NOT clone the repo straight into `local/sm_graphics_plugin/`** — `frontend_dist/` is gitignored and built by CI on every release. The installer detects this and shows a clear error if it's missing.

### Development

#### 1. Clone

```bash
git clone https://github.com/SmartmindTech/SM_Aprendizaje_Ilimitado_Plugin.git
cd SM_Aprendizaje_Ilimitado_Plugin
```

#### 2. Configure `.env`

```bash
cp .env.example .env
```

Edit the relevant variables:
```bash
MOODLE_PATH=/path/to/Moodle     # Only for scripts/linux/ non-Docker setups
DOCKER_CONTAINER=iomad_app       # Docker container name
GIT_BRANCH=devPaulo              # Your personal branch
UPDATE_BRANCH=latest             # Update channel (latest = release asset)
SPA_DEV=1                        # Enables HMR via the Nuxt dev server
SPA_DEV_PORT=4173                # Nuxt dev server port
GEMINI_API_KEY=...               # For learning-objective auto-translation
SMTP_*, AZURE_*                  # If you use SharePoint + email
```

#### 3. Install frontend dependencies

```bash
cd frontend && npm install && cd ..
```

#### 4. Start the watcher (single command)

```bash
./scripts/docker_linux/watch.sh         # Linux/WSL
.\scripts\docker_windows\watch.ps1      # Windows
```

This does three things:
- Spawns `npm run dev` (Nuxt dev server) in the background → instant HMR for Vue/SCSS/i18n
- Watches PHP/lang/db/classes/theme changes and syncs them into the container via `sync-theme.sh`
- Cleans up the dev server on exit (Ctrl+C)

Nuxt dev server logs: `scripts/docker_linux/.nuxt-dev.log`.

#### 5. Open Moodle

Always use the Moodle URL (not the Nuxt dev server URL):

```
http://localhost:8081/local/sm_graphics_plugin/pages/spa.php
```

`spa.php` detects the dev server (via `SPA_DEV=1` and `SPA_DEV_PORT`), proxies its HTML, and injects the bootstrap. The session cookie works because both ports share the `localhost` domain.

Any edit to `frontend/app/**`, `frontend/i18n/**`, `frontend/assets/**` → HMR reload in <300 ms with no manual rebuild.

### Slower workflow without HMR

If you don't want the dev server, set `SPA_DEV=0` in `.env` and rebuild manually whenever you edit Vue:

```bash
cd frontend && npm run deploy && cd ..
./scripts/docker_linux/sync-theme.sh
```

`spa.php` will then serve the static `frontend_dist/`.

## Where to code what

| I want to change... | Edit in... |
|---|---|
| A user-facing page | `frontend/app/pages/*.vue` |
| A reusable component | `frontend/app/components/*.vue` |
| Styles | `frontend/app/assets/scss/` |
| Moodle API calls | `frontend/app/composables/api_calls/` |
| Global state | `frontend/app/stores/` |
| Frontend translations | `frontend/i18n/locales/{es,en,pt_br}.json` |
| Backend language strings | `lang/{es,en,pt_br}/local_sm_graphics_plugin.php` |
| An external function (API) | `classes/external/*.php` + `db/services.php` |
| DB schema | `db/install.xml` + `db/upgrade.php` (with savepoint) |
| Course form hooks | `classes/hook/course_form_handler.php` |
| Event observers | `classes/observer.php` + `db/events.php` |
| Scheduled tasks | `classes/task/*.php` + `db/tasks.php` |
| Theme (layouts, SCSS for core pages) | `theme_smartmind/` |

## Creating a new Vue page

1. Create `frontend/app/pages/my-page.vue` — the route is auto-generated as `#/my-page`.
2. If you need Moodle data, create an external function in `classes/external/get_my_data.php` and register it in `db/services.php`.
3. Call it from a composable:
   ```vue
   <script setup lang="ts">
   const { call } = useMoodleAjax()
   const result = await call('local_sm_graphics_plugin_get_my_data', { param: 123 })
   </script>
   ```
4. Bump `version.php` (`YYYYMMDDXX` format) if you added backend or DB code.

## Releases and updates

### Automatic releases (CI)

Any `push` to `main`, `dev`, or `testnuxt` triggers `.github/workflows/release.yml`, which:

1. Runs `npm ci && npm run deploy` to build the SPA
2. Packages the plugin into a ZIP (excluding `frontend/`, `node_modules/`, `frontend/.nuxt/`, `frontend/.output/`)
3. Generates `update.xml` from the current version
4. Creates a GitHub release with the ZIP and `update.xml` as assets
5. Marks the release as `--latest`

You never have to build manually to publish — just bump `version.php` and push.

### In-UI updates

Installed Moodles see new releases automatically:

- **Site admin → Notifications → "Check for available updates"** detects the release via `https://github.com/.../releases/latest/download/update.xml` (~1 min CDN cache from GitHub Releases)
- The plugin settings page has an **"Install update"** button that downloads the ZIP, extracts it, and applies the files
- If the installed version matches the latest release but local files are stale (e.g. after a partial sync), a **"Force reinstall latest"** button re-applies the release on demand

### Channel selection via `UPDATE_BRANCH`

| Value | Behaviour |
|---|---|
| `latest` (default) | `releases/latest/download/update.xml` — auto-resolves to the most recent release, ~1 min cache |
| `main` / `dev` / `testnuxt` / `devPaulo` / ... | `raw.githubusercontent.com/<branch>/update.xml` — useful for pinning a Moodle to a specific dev branch, ~5 min cache |

## Git

Branches: `main` (release), `dev` (integration), `testnuxt` (Vue migration line), `devPaulo`/`devDiego`/`devAntonio` (personal).

```bash
git checkout devPaulo
git pull --rebase origin testnuxt   # or dev/main depending on where you're working
# ... changes ...
git add .
git commit -m "Brief description"
git push origin devPaulo
```

To integrate into `testnuxt`:
```bash
git checkout testnuxt
git pull --rebase
git merge devPaulo
git push origin testnuxt
```

`dump.html`, `image*.png`, `.env`, `frontend_dist/`, `frontend/node_modules/`, `frontend/.nuxt/`, `frontend/.output/`, and `CLAUDE.md` are gitignored — never commit them.

## Requirements

- Moodle 5.0+, PHP 8.2+, IOMAD (optional but recommended)
- Node.js 20+, npm
- Docker Desktop / WSL2 with `host.docker.internal` (so `spa.php` can reach the Nuxt dev server from inside the container)
