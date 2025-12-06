export type AdsProvider = 'ethical' | 'adsense' | 'none'

let currentProvider: AdsProvider = 'none'
let providerInitialized = false

let ethicalScriptLoaded = false
let adsenseScriptLoaded = false

const REAL_ADS_ENABLED = import.meta.env.VITE_ENV === "production";

export function initAds(provider: AdsProvider) {
  if (!REAL_ADS_ENABLED) {
    currentProvider = 'none'
  } else {
    currentProvider = provider
  }
}

export function loadAds() {
  if (!REAL_ADS_ENABLED) return
  if (providerInitialized) return
  providerInitialized = true

  if (currentProvider === 'ethical') {
    loadEthicalAdsScript()
  } else if (currentProvider === 'adsense') {
    loadAdsenseScript()
  } else {
  }
}

export function renderSlot(slotId: string, options?: Record<string, any>) {
  if (currentProvider === 'ethical') {
    renderEthicalSlot(slotId, options)
  } else if (currentProvider === 'adsense') {
    renderAdsenseSlot(slotId, options)
  } else {
  }
}

function loadEthicalAdsScript() {
  if (ethicalScriptLoaded) return
  ethicalScriptLoaded = true

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://media.ethicalads.io/media/client/ethicalads.min.js'
  script.id = 'ethicalads-script'
  document.head.appendChild(script)
}

/**
 * EthicalAds fonctionne via des <div data-ea-publisher="..." data-ea-type="..."></div>
 * Ici, on laisse le composant Vue générer le markup, et on demande
 * à EthicalAds de re-scanner la page.
 * @param slotId
 * @param options
 */
function renderEthicalSlot(slotId: string, options?: Record<string, any>) {

  const w = window as any
  if (!w.ethicalads || typeof w.ethicalads.load_placements !== 'function') {
    return
  }

  try {
    w.ethicalads.load_placements()
  } catch (e) {
    console.error('[KCDLE] Error while loading EthicalAds placements:', e)
  }
}


function loadAdsenseScript() {
  if (adsenseScriptLoaded) return
  adsenseScriptLoaded = true

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'
  script.setAttribute('data-ad-client', 'ca-pub-XXXXXXXXXXXXXXX')
  document.head.appendChild(script)
}

function renderAdsenseSlot(slotId: string, options?: Record<string, any>) {
  const w = window as any
  if (!w.adsbygoogle) {
    w.adsbygoogle = []
  }

  try {
    w.adsbygoogle.push({})
  } catch (e) {
    console.error('[KCDLE] Error while loading AdSense ads:', e)
  }
}
