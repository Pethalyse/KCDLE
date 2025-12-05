let adsLoaded = false

export function loadAds() {
  if (adsLoaded) return
  adsLoaded = true

  console.log("[KCDLE] Ads consent accepted. Ad network loader ready.")

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://example-ad-network.com/script.js'
  document.head.appendChild(script)
}
