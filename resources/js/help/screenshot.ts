/**
 * Bildschirmfoto des sichtbaren Ausschnitts als JPEG, ohne den Hilfe-Knopf und -Dialog.
 * Schlägt es fehl oder dauert es zu lange, geht die Meldung ohne Bild.
 */
const MAX_WIDTH = 1600;
const MAX_PIXELS = 12_000_000;
const MAX_BYTES = 3_500_000;
const TIMEOUT_MS = 8000;

function background(): string {
    for (const el of [document.body, document.documentElement]) {
        const color = getComputedStyle(el).backgroundColor;

        if (color && color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') {
            return color;
        }
    }

    return document.documentElement.classList.contains('dark')
        ? '#0a0a0a'
        : '#ffffff';
}

function blank(cloned: Node): void {
    if (
        cloned instanceof HTMLInputElement &&
        (cloned.type === 'password' ||
            /pin|password|otp|token|secret/i.test(
                `${cloned.name} ${cloned.id}`,
            ) ||
            /password|one-time-code/i.test(cloned.autocomplete))
    ) {
        cloned.value = '';
        cloned.setAttribute('value', '');
    }
}

function toBlob(
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob | null> {
    return new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', quality),
    );
}

async function capture(exclude: Element | null): Promise<Blob | null> {
    const { domToCanvas } = await import('modern-screenshot');
    const root = document.documentElement;
    const fullWidth = Math.max(root.scrollWidth, window.innerWidth);
    const fullHeight = Math.max(root.scrollHeight, window.innerHeight);
    const scale = Math.min(
        window.devicePixelRatio || 1,
        MAX_WIDTH / window.innerWidth,
        Math.sqrt(MAX_PIXELS / (fullWidth * fullHeight)),
    );

    const full = await domToCanvas(root, {
        scale,
        backgroundColor: background(),
        filter: (node) => node !== exclude,
        onCloneEachNode: blank,
        timeout: 3000,
    });

    // Auf den sichtbaren Ausschnitt zuschneiden: das sieht der Nutzer gerade.
    const crop = document.createElement('canvas');
    crop.width = Math.round(window.innerWidth * scale);
    crop.height = Math.round(window.innerHeight * scale);
    const context = crop.getContext('2d');

    if (!context) {
        return null;
    }

    context.fillStyle = background();
    context.fillRect(0, 0, crop.width, crop.height);
    context.drawImage(
        full,
        Math.round(window.scrollX * scale),
        Math.round(window.scrollY * scale),
        crop.width,
        crop.height,
        0,
        0,
        crop.width,
        crop.height,
    );

    for (const quality of [0.8, 0.6, 0.4]) {
        const blob = await toBlob(crop, quality);

        if (blob && blob.size <= MAX_BYTES) {
            return blob;
        }
    }

    return null;
}

export async function captureScreenshot(
    exclude: Element | null,
): Promise<Blob | null> {
    try {
        return await Promise.race([
            capture(exclude),
            new Promise<null>((resolve) =>
                setTimeout(() => resolve(null), TIMEOUT_MS),
            ),
        ]);
    } catch (error) {
        console.warn(
            'Bildschirmfoto für die Hilfe-Meldung nicht möglich',
            error,
        );

        return null;
    }
}
