/**
 * Composable central para formateo y parseo de importes con soporte multi-moneda.
 *
 * - Refleja `config/currencies.php` (servidor) vía `props.currencies` que el
 *   backend expone por Inertia share (ver HandleInertiaRequests).
 * - Toda Vue component que renderice dinero debe usar `fmt(amount, currency)`.
 *   Si no se pasa currency, se usa la default del sistema (MXN).
 *
 * API:
 *   const { fmt, parse, options, base, defaultCurrency, isSupported } = useMoney()
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Fallback estático si Inertia aún no compartió `currencies` (boot, tests).
const FALLBACK = {
    base: 'MXN',
    default: 'MXN',
    supported: {
        MXN: { code: 'MXN', name: 'Peso mexicano',          symbol: '$',  locale: 'es-MX', decimals: 2 },
        USD: { code: 'USD', name: 'Dólar estadounidense',   symbol: 'US$', locale: 'en-US', decimals: 2 },
    },
}

export function useMoney () {
    const page = (() => { try { return usePage() } catch { return null } })()

    const config = computed(() => {
        const shared = page?.props?.currencies
        if (shared && shared.supported && Object.keys(shared.supported).length) return shared
        return FALLBACK
    })

    const base = computed(() => config.value.base || 'MXN')
    const defaultCurrency = computed(() => config.value.default || base.value)
    const options = computed(() =>
        Object.values(config.value.supported).map(m => ({ value: m.code, label: `${m.code} — ${m.name}` }))
    )

    const isSupported = (code) => !!(code && config.value.supported[String(code).toUpperCase()])

    /**
     * Formatea importe con Intl.NumberFormat usando el locale de la divisa.
     * Tolera nulos/strings; SI la divisa es desconocida, cae a base.
     */
    const fmt = (amount, currency) => {
        const cur = (currency || defaultCurrency.value || 'MXN').toUpperCase()
        const meta = config.value.supported[cur] || config.value.supported[base.value] || FALLBACK.supported.MXN
        const n = Number(amount)
        return new Intl.NumberFormat(meta.locale || 'es-MX', {
            style: 'currency',
            currency: meta.code,
            minimumFractionDigits: meta.decimals ?? 2,
            maximumFractionDigits: meta.decimals ?? 2,
        }).format(Number.isFinite(n) ? n : 0)
    }

    /** Parsea "$1,234.56" → 1234.56. Útil en inputs custom. */
    const parse = (raw) => {
        if (raw == null || raw === '') return 0
        const cleaned = String(raw).replace(/[^\d.-]/g, '')
        const n = Number(cleaned)
        return Number.isFinite(n) ? n : 0
    }

    /**
     * Convierte importe entre divisas usando el mapa de tasas que comparte
     * Inertia (`config.rates`): { CURRENCY => rate-to-base, BASE: 1 }.
     *
     * - from === to ➜ regresa el mismo amount.
     * - Si falta tasa para `from` o `to`, regresa null (el caller decide cómo
     *   mostrar el caso "tasa no disponible").
     * - Para divisa no-base ↔ no-base trianguliza vía base.
     */
    const convert = (amount, from, to) => {
        const n = Number(amount)
        if (!Number.isFinite(n)) return 0
        const f = (from || base.value).toUpperCase()
        const t = (to   || base.value).toUpperCase()
        if (f === t) return n
        const rates = config.value.rates || {}
        const rFromToBase = f === base.value ? 1 : rates[f]
        const rToToBase   = t === base.value ? 1 : rates[t]
        if (!rFromToBase || !rToToBase) return null
        // amountBase = n * rFromToBase ; result = amountBase / rToToBase
        return Math.round((n * rFromToBase / rToToBase) * 100) / 100
    }

    /** ¿Tenemos tasa para esta divisa? (Útil para mostrar warnings.) */
    const hasRate = (code) => {
        if (!code) return false
        const c = String(code).toUpperCase()
        if (c === base.value) return true
        return !!(config.value.rates && config.value.rates[c])
    }

    return { fmt, parse, convert, hasRate, options, base, defaultCurrency, isSupported, config }
}

export default useMoney
