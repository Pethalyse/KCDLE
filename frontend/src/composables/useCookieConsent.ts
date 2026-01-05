import { ref } from 'vue'
import { loadPlausible } from '@/analytics'
import { loadAds } from '@/ads'
import {handleError} from "@/utils/handleError.ts";

interface Consent {
  essential: true
  analytics: boolean
  ads: boolean
  decidedAt: string
}

const STORAGE_KEY = 'kcdle-consent-v1'

const visible = ref(false)
const showDetails = ref(false)
const analyticsChecked = ref(false)
const adsChecked = ref(false)

let initialized = false

function initIfNeeded() {
  if (initialized) return
  initialized = true

  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      visible.value = true
      return
    }

    const consent = JSON.parse(raw) as Consent
    analyticsChecked.value = consent.analytics
    adsChecked.value = consent.ads

    if (consent.analytics) {
      loadPlausible()
    }

    if (consent.ads) {
      loadAds()
    }
  } catch (e) {
    handleError(e, 'Erreur de lecture du consentement cookies :')
    visible.value = true
  }
}

function saveConsent(partial: { analytics: boolean; ads: boolean }) {
  const consent: Consent = {
    essential: true,
    analytics: partial.analytics,
    ads: partial.ads,
    decidedAt: new Date().toISOString(),
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(consent))

  if (consent.analytics) {
    loadPlausible()
  }

  if (consent.ads) {
    loadAds()
  }

  visible.value = false
}

function acceptAll() {
  analyticsChecked.value = true
  adsChecked.value = true
  saveConsent({ analytics: true, ads: true })
}

function refuseAll() {
  analyticsChecked.value = false
  adsChecked.value = false
  saveConsent({ analytics: false, ads: false })
}

function savePreferences() {
  saveConsent({ analytics: analyticsChecked.value, ads: adsChecked.value })
}

function toggleDetails() {
  showDetails.value = !showDetails.value
}

function openCookieManager() {
  initIfNeeded()
  visible.value = true
  showDetails.value = true
}

export function useCookieConsent() {
  initIfNeeded()

  return {
    visible,
    showDetails,
    analyticsChecked,
    adsChecked,
    acceptAll,
    refuseAll,
    savePreferences,
    toggleDetails,
    openCookieManager,
  }
}
