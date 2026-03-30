// @ts-ignore - bootstrap does not have TypeScript declarations
import * as bootstrap from 'bootstrap'

declare global {
  interface Window {
    bootstrap: any
  }
}

export default defineNuxtPlugin(() => {
  if (import.meta.client) {
    (window as Window).bootstrap = bootstrap as any
  }
})
