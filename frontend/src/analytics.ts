import type { Router } from 'vue-router'

let plausibleLoaded = false

declare global {
  interface Window {
    plausible?: ((eventName: string, options?: any) => void) & {
      q?: any[]
      o?: any
      init?: (options?: any) => void
    }
  }
}

export function loadPlausible() {
  if (plausibleLoaded) return
  plausibleLoaded = true

  window.plausible =
    window.plausible ||
    (function (...args: any[]) {
      ;(window.plausible!.q = window.plausible!.q || []).push(args)
    } as any)

  if(!window.plausible) return;

  window.plausible.init =
    window.plausible.init ||
    function (options?: any) {
      window.plausible!.o = options || {}
    }

  window.plausible.init()

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://plausible.io/js/pa-GORI-k-eTe7orzQhVzCbf.js'
  document.head.appendChild(script)
}

export function trackEvent(eventName: string, props?: Record<string, any>) {
  if (typeof window === 'undefined') return
  if (typeof window.plausible !== 'function') return

  if (props && Object.keys(props).length > 0) {
    window.plausible(eventName, { props })
  } else {
    window.plausible(eventName)
  }
}

export function initRouterAnalytics(router: Router) {
  router.afterEach((to) => {
    if (typeof window === 'undefined') return
    if (typeof window.plausible !== 'function') return

    window.plausible('pageview', {
      props: {
        path: to.fullPath,
        routeName: to.name ?? null,
      },
    })
  })
}
