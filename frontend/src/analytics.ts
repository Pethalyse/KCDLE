let plausibleLoaded = false

export function loadPlausible() {
  if (plausibleLoaded) return
  plausibleLoaded = true

  ;(window as any).plausible =
    (window as any).plausible ||
    function () {
      ;((window as any).plausible.q = (window as any).plausible.q || []).push(arguments)
    }

  ;(window as any).plausible.init =
    (window as any).plausible.init ||
    function (options: any) {
      ;(window as any).plausible.o = options || {}
    }

  ;(window as any).plausible.init()

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://plausible.io/js/pa-GORI-k-eTe7orzQhVzCbf.js'
  document.head.appendChild(script)
}
