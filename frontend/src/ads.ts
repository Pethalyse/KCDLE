import { ref } from 'vue'
import { handleError } from '@/utils/handleError.ts'

export type AdsProvider = 'ethical' | 'adsense' | 'none'

export const adsProvider = ref<AdsProvider>('none')
let preferredProvider: AdsProvider = 'none'
let providerInitialized = false

let ethicalScriptLoaded = false
let adsenseScriptLoaded = false

let adsensePersonalizedAllowed = false

const GOOGLE_CMP_ENABLED = import.meta.env.VITE_GOOGLE_CMP_ENABLED === '1'

const REAL_ADS_ENABLED = import.meta.env.VITE_ENV === 'production'

const PUBLISHER_ID = (import.meta.env.VITE_PUBLISHER_ID as string | undefined) || ''
const AD_SENSE_ID = (import.meta.env.VITE_AD_SENSE_ID as string | undefined) || ''

export function initAds(provider: AdsProvider) {
  if (!REAL_ADS_ENABLED) {
    preferredProvider = 'none'
    adsProvider.value = 'none'
  } else {
    preferredProvider = provider
    adsProvider.value = provider
  }
}

export function autoInitAds() {
  if (!REAL_ADS_ENABLED) {
    initAds('none')
    return
  }

  const forced = (import.meta.env.VITE_ADS_PROVIDER as string | undefined) || ''
  if (forced === 'ethical' || forced === 'adsense' || forced === 'none') {
    initAds(forced)
    return
  }

  if (AD_SENSE_ID) {
    initAds('adsense')
    return
  }

  if (PUBLISHER_ID) {
    initAds('ethical')
    return
  }

  initAds('none')
}

export function loadAds() {
  if (!REAL_ADS_ENABLED) return
  if (providerInitialized) return
  providerInitialized = true

  if (adsProvider.value === 'ethical') {
    loadEthicalAdsScript()
    return
  }

  if (adsProvider.value === 'adsense') {
    loadAdsenseScript()
    return
  }
}

export function renderSlot(slotId: string, options?: Record<string, any>) {
  if (adsProvider.value === 'ethical') {
    renderEthicalSlot(slotId, options)
  } else if (adsProvider.value === 'adsense') {
    renderAdsenseSlot(slotId, options)
  }
}

function loadEthicalAdsScript() {
  if (ethicalScriptLoaded) return
  ethicalScriptLoaded = true

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://media.ethicalads.io/media/client/ethicalads.min.js'
  script.id = 'ethicalads-script'

  script.onerror = () => {
    adsProvider.value = 'none'
  }

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
    handleError('[KCDLE] Error while loading EthicalAds placements:' + e)
  }
}

function loadAdsenseScript() {
  if (adsenseScriptLoaded) return
  const existing = document.querySelector(
    'script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]'
  ) as HTMLScriptElement | null

  if (existing) {
    adsenseScriptLoaded = true
    return
  }

  adsenseScriptLoaded = true

  if (!GOOGLE_CMP_ENABLED) {
    setAdsensePersonalizedAllowed(adsensePersonalizedAllowed)
  }

  const script = document.createElement('script')
  script.async = true
  script.crossOrigin = 'anonymous'
  script.src = AD_SENSE_ID
    ? `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(AD_SENSE_ID)}`
    : 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'

  script.onerror = () => {
    try {
      if (preferredProvider === 'adsense' && PUBLISHER_ID) {
        adsProvider.value = 'ethical'
        loadEthicalAdsScript()
        return
      }
      adsProvider.value = 'none'
    } catch (e) {
      handleError('[KCDLE] Error while handling AdSense script failure:' + e)
      adsProvider.value = 'none'
    }
  }

  document.head.appendChild(script)
}

export function setAdsensePersonalizedAllowed(allowed: boolean) {
  adsensePersonalizedAllowed = allowed

  if (GOOGLE_CMP_ENABLED) {
    return
  }

  const w = window as any
  w.adsbygoogle = w.adsbygoogle || []

  if (!adsensePersonalizedAllowed) {
    w.adsbygoogle.requestNonPersonalizedAds = 1
  } else {
    w.adsbygoogle.requestNonPersonalizedAds = 0
  }
}

function renderAdsenseSlot(slotId: string, options?: Record<string, any>) {
  const w = window as any
  if (!w.adsbygoogle) {
    w.adsbygoogle = []
  }

  try {
    w.adsbygoogle.push({})
  } catch (e) {
    handleError('[KCDLE] Error while loading AdSense ads:' + e)
  }
}
