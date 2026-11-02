<script lang="ts">
    import QRCode from 'qrcode';

    let { value, size = 280 }: { value: string; size?: number } = $props();

    let canvas = $state<HTMLCanvasElement>();

    $effect(() => {
        if (canvas) {
            QRCode.toCanvas(canvas, value, { width: size, margin: 1, errorCorrectionLevel: 'M' });
        }
    });
</script>

<canvas bind:this={canvas} width={size} height={size} aria-label="QR-Code zum Anmelden am Stand"></canvas>
