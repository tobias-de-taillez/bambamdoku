<script lang="ts">
    let { class: className = '' }: { class?: string } = $props();
</script>

<!-- Zielscheibe: drei Ringe und Mittelpunkt -->
<svg
    viewBox="0 0 24 24"
    class={className}
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    aria-hidden="true"
>
    <circle cx="12" cy="12" r="10" />
    <circle cx="12" cy="12" r="6" />
    <circle cx="12" cy="12" r="2" fill="currentColor" stroke="none" />
</svg>
