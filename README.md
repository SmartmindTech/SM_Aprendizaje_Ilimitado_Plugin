# SmartMind Graphic Layer Plugin

**[Español](#español) | [English](#english)**

---

# Español

Plugin local de Moodle que proporciona una capa grafica personalizada para la plataforma de aprendizaje SmartMind, junto con el tema SmartMind.

## Componentes

- **`local_sm_graphics_plugin`** — API de matriculacion, paginas personalizadas, configuracion de administracion, actualizacion automatica
- **`theme_smartmind`** — Tema hijo de Boost con layouts personalizados, barra lateral, SCSS ([submodulo git](https://github.com/SmartmindTech/SM_Theme_Moodle))

## Estructura del proyecto

```
SM_Moodle_Graphic_Layer_Plugin/
├── version.php, lib.php, settings.php    Archivos principales del plugin
├── db/                                   Install, upgrade, hooks, events
├── pages/                                Paginas personalizadas (welcome, etc.)
├── templates/                            Templates Mustache
├── lang/en/, lang/es/                    Cadenas de idioma
├── classes/                              Clases PHP (hooks, observers)
├── theme_smartmind/                      Tema (submodulo git → repo SM_Theme_Moodle)
├── setup.bat / setup.sh                  Configuracion inicial de desarrollo
├── sync-theme.bat / sync-theme.sh        Sincronizar archivos + purgar cache
├── watch.bat / watch.sh                  Purgar cache automaticamente al guardar
├── git-push.bat / git-push.sh            Commit + push del plugin y tema
├── git-pull.bat / git-pull.sh            Pull del plugin y tema
└── .env.example                          Plantilla de configuracion
```

## Instalacion

### Produccion

Sube `sm_graphics_plugin.zip` en **Administracion del sitio > Plugins > Instalar plugins**. El tema viene incluido y se despliega automaticamente.

### Desarrollo

#### Paso 1 — Clonar el repositorio

```bash
git clone --recurse-submodules https://github.com/SmartmindTech/SM_Moodle_Graphic_Layer_Plugin.git
cd SM_Moodle_Graphic_Layer_Plugin
cd theme_smartmind && git checkout dev && cd ..
```

Si ya clonaste sin `--recurse-submodules`:

```bash
git submodule init && git submodule update
cd theme_smartmind && git checkout dev && cd ..
```

#### Paso 2 — Configurar la ruta de Moodle

```bash
copy .env.example .env     # Windows
cp .env.example .env       # WSL/Linux
```

Edita `.env` y configura:
- `MOODLE_PATH` — raiz de tu instalacion de Moodle (la carpeta que contiene `admin/`, `theme/`, `local/`)
- `GIT_BRANCH` — tu rama de desarrollo (ej: `devPaulo`, `devDiego`, `devAntonio`)

#### Paso 3 — Ejecutar setup

**Windows (cmd como Administrador):**
```cmd
setup.bat
```

Esto crea **junctions** para que Moodle lea directamente del repositorio. **Los cambios en archivos PHP toman efecto inmediatamente.** Para cambios en templates y SCSS, necesitas purgar la cache (ver siguiente seccion).

**WSL/Linux/Mac:**
```bash
./setup.sh
```

Copia archivos a Moodle. Despues de hacer cambios, necesitas sincronizar (ver siguiente seccion).

## Flujo de desarrollo

### Despues de editar archivos

Edita los archivos del plugin en la raiz del repo y los del tema dentro de `theme_smartmind/`.

#### Windows (con junctions)

Los cambios en PHP son instantaneos. Para cambios en templates/SCSS, purga la cache:

```cmd
sync-theme.bat
```

O mantiene el watcher activo — purga la cache automaticamente al guardar un archivo:

```cmd
watch.bat
```

#### WSL/Linux

Sincroniza archivos a Moodle y purga la cache:

```bash
./sync-theme.sh
```

O mantiene el watcher activo para sincronizar automaticamente:

```bash
./watch.sh
```

### Hacer commit y push

Usa los scripts para hacer commit y push del plugin y del tema en un solo comando:

```bash
git-push.bat "Tu mensaje de commit"     # Windows
./git-push.sh "Tu mensaje de commit"    # WSL/Linux/Mac
```

El script hace todo automaticamente:
1. Detecta que cambio (plugin, tema, o ambos)
2. Hace commit y push en tu rama (ej: `devPaulo`)
3. Hace merge de tu rama en `dev` y push de `dev`
4. Vuelve a tu rama
5. Sincroniza Moodle y purga caches

### Obtener los ultimos cambios

```bash
git-pull.bat         # Windows
./git-pull.sh        # WSL/Linux/Mac
```

El script hace:
1. Trae los ultimos cambios de `dev` y los mergea en tu rama (ej: `devPaulo`)
2. Trae los ultimos cambios del tema
3. Sincroniza Moodle y purga caches

### Git manual (opcional)

Tambien puedes usar git directamente en vez de los scripts:

**Plugin:**
```bash
git add <archivos> && git commit -m "mensaje" && git push origin devPaulo
git checkout dev && git pull origin dev && git merge devPaulo && git push origin dev
git checkout devPaulo
```

**Tema** (commit en ambos repos):
```bash
cd theme_smartmind
git add . && git commit -m "mensaje" && git push origin dev
cd ..
git add theme_smartmind && git commit -m "Update theme submodule" && git push origin devPaulo
```

## Resumen de scripts

| Script | Que hace |
|--------|----------|
| `setup.bat` / `setup.sh` | Setup inicial: init submodulo, vincular/copiar a Moodle, purgar cache |
| `sync-theme.bat` / `sync-theme.sh` | Sincronizar archivos (si es necesario) + purgar cache de Moodle |
| `watch.bat` / `watch.sh` | Purgar cache automaticamente al guardar (mantener corriendo en una terminal) |
| `git-push.bat` / `git-push.sh` | Commit + push en tu rama, merge en dev, sync Moodle |
| `git-pull.bat` / `git-pull.sh` | Merge dev en tu rama, pull tema, sync Moodle |

## Requisitos

- Moodle 5.0+
- PHP 8.2+
- theme_boost (viene con Moodle)

---

# English

A Moodle local plugin that provides a custom graphic layer for SmartMind's learning platform, bundled with the SmartMind theme.

## Components

- **`local_sm_graphics_plugin`** — enrollment API, custom pages, admin settings, auto-update mechanism
- **`theme_smartmind`** — Boost child theme with custom layouts, sidebar, SCSS overrides ([git submodule](https://github.com/SmartmindTech/SM_Theme_Moodle))

## Project Structure

```
SM_Moodle_Graphic_Layer_Plugin/
├── version.php, lib.php, settings.php    Plugin core files
├── db/                                   Install, upgrade, hooks, events
├── pages/                                Custom pages (welcome, etc.)
├── templates/                            Mustache templates
├── lang/en/, lang/es/                    Language strings
├── classes/                              PHP classes (hooks, observers)
├── theme_smartmind/                      Theme (git submodule → SM_Theme_Moodle repo)
├── setup.bat / setup.sh                  First-time dev setup
├── sync-theme.bat / sync-theme.sh        Sync files + purge caches
├── watch.bat / watch.sh                  Auto-purge caches on file changes
├── git-push.bat / git-push.sh            Commit + push plugin and theme
├── git-pull.bat / git-pull.sh            Pull plugin and theme
└── .env.example                          Environment config template
```

## Installation

### Production

Upload `sm_graphics_plugin.zip` via **Site Administration > Plugins > Install plugins**. The theme is bundled and deployed automatically.

### Development

#### Step 1 — Clone the repo

```bash
git clone --recurse-submodules https://github.com/SmartmindTech/SM_Moodle_Graphic_Layer_Plugin.git
cd SM_Moodle_Graphic_Layer_Plugin
cd theme_smartmind && git checkout dev && cd ..
```

If you already cloned without `--recurse-submodules`:

```bash
git submodule init && git submodule update
cd theme_smartmind && git checkout dev && cd ..
```

#### Step 2 — Configure Moodle path

```bash
copy .env.example .env     # Windows
cp .env.example .env       # WSL/Linux
```

Edit `.env` and configure:
- `MOODLE_PATH` — your Moodle installation root (the folder containing `admin/`, `theme/`, `local/`)
- `GIT_BRANCH` — your development branch (e.g., `devPaulo`, `devDiego`, `devAntonio`)

#### Step 3 — Run setup

**Windows (cmd as Administrator):**
```cmd
setup.bat
```

This creates **junctions** so Moodle reads directly from the repo. **Changes to PHP files take effect immediately.** For template and SCSS changes, caches need to be purged (see next section).

**WSL/Linux/Mac:**
```bash
./setup.sh
```

Copies files to Moodle. After changes, you need to sync (see next section).

## Development Workflow

### After editing files

Edit plugin files in the repo root and theme files inside `theme_smartmind/`.

#### Windows (with junctions)

PHP changes are instant. For template/SCSS changes, purge caches:

```cmd
sync-theme.bat
```

Or keep the watcher running — it auto-purges caches on every file save:

```cmd
watch.bat
```

#### WSL/Linux

Sync files to Moodle and purge caches:

```bash
./sync-theme.sh
```

Or keep the watcher running for auto-sync:

```bash
./watch.sh
```

### Committing and pushing

Use the helper scripts to commit and push both plugin and theme in one command:

```bash
git-push.bat "Your commit message"     # Windows
./git-push.sh "Your commit message"    # WSL/Linux/Mac
```

The script auto-detects what changed:
- **Only plugin changed** — commits and pushes the plugin
- **Only theme changed** — commits to the theme repo, then updates the submodule in the plugin repo
- **Both changed** — handles both with the same commit message

### Pulling latest changes

```bash
git-pull.bat         # Windows
./git-pull.sh        # WSL/Linux/Mac
```

Pulls latest changes for both the plugin and the theme submodule.

### Manual git (optional)

You can always use git directly instead of the helper scripts:

**Plugin:**
```bash
git add <files> && git commit -m "message" && git push origin dev
```

**Theme** (commit to both repos):
```bash
cd theme_smartmind
git add . && git commit -m "message" && git push origin dev
cd ..
git add theme_smartmind && git commit -m "Update theme submodule" && git push origin dev
```

## Script Summary

| Script | What it does |
|--------|-------------|
| `setup.bat` / `setup.sh` | First-time setup: init submodule, link/copy to Moodle, purge caches |
| `sync-theme.bat` / `sync-theme.sh` | Sync files (if needed) + purge Moodle caches |
| `watch.bat` / `watch.sh` | Auto-purge caches on file save (keep running in a terminal) |
| `git-push.bat` / `git-push.sh` | Commit + push plugin and theme in one command |
| `git-pull.bat` / `git-pull.sh` | Pull latest plugin + theme changes |

## Requirements

- Moodle 5.0+
- PHP 8.2+
- theme_boost (ships with Moodle)
