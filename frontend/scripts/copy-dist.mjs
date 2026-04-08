// Cross-platform replacement for the bash one-liner that used to live in
// package.json's `deploy` script. `cp -r` and `mkdir -p` don't exist on
// Windows cmd.exe (npm's default shell on Windows), so the previous version
// failed silently after `nuxt generate` succeeded, leaving frontend_dist/
// half-populated and breaking spa.php which expects index.html.
//
// This script wipes frontend_dist/ and copies the fresh nuxt output into it.
// Wiping is intentional: stale hashed _nuxt/*.js files from older builds would
// otherwise pile up forever.

import { readdirSync, rmSync, mkdirSync, cpSync } from 'node:fs'
import { resolve, join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const src = resolve(here, '..', '.output', 'public')
const dst = resolve(here, '..', '..', 'frontend_dist')

// Clear contents of frontend_dist/ rather than removing the directory itself.
// On Windows, deleting the top-level directory often fails with EPERM when the
// IDE/antivirus has a handle on it.
mkdirSync(dst, { recursive: true })
for (const entry of readdirSync(dst)) {
  rmSync(join(dst, entry), { recursive: true, force: true })
}

cpSync(src, dst, { recursive: true })

console.log(`Deployed SPA: ${src} -> ${dst}`)
