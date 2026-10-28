<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'

// Widget anti-bot Cloudflare Turnstile. Hanya dirender bila parent memberi siteKey (lihat prop bersama
// "turnstile_site_key"). Token hasil verifikasi dikirim lewat event "verified".
const props = defineProps({ siteKey: { type: String, required: true } })
const emit = defineEmits(['verified', 'expired'])

const el = ref(null)
let widgetId = null

function render() {
  if (!window.turnstile || !el.value || widgetId !== null) return
  widgetId = window.turnstile.render(el.value, {
    sitekey: props.siteKey,
    callback: (token) => emit('verified', token),
    'expired-callback': () => emit('expired'),
    'error-callback': () => emit('expired'),
  })
}

onMounted(() => {
  if (window.turnstile) return render()
  let script = document.querySelector('script[data-turnstile]')
  if (!script) {
    script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.dataset.turnstile = '1'
    document.head.appendChild(script)
  }
  script.addEventListener('load', render)
})

onBeforeUnmount(() => {
  if (widgetId !== null && window.turnstile) window.turnstile.remove(widgetId)
})

defineExpose({ reset: () => { if (widgetId !== null && window.turnstile) window.turnstile.reset(widgetId) } })
</script>

<template>
  <div ref="el" class="min-h-[65px]"></div>
</template>
