<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Eye from '@lucide/svelte/icons/eye';
    import EyeOff from '@lucide/svelte/icons/eye-off';
    import LockKeyhole from '@lucide/svelte/icons/lock-keyhole';
    import RefreshCw from '@lucide/svelte/icons/refresh-cw';
    import { onMount, tick } from 'svelte';
    import AlertError from '@/components/AlertError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { twoFactorAuthState } from '@/lib/twoFactorAuth.svelte';
    import { regenerateRecoveryCodes } from '@/routes/two-factor';

    const twoFactorAuth = twoFactorAuthState();
    let isRecoveryCodesVisible = $state(false);
    let recoveryCodeSectionRef = $state<HTMLDivElement | undefined>();

    async function toggleRecoveryCodesVisibility() {
        if (
            !isRecoveryCodesVisible &&
            !twoFactorAuth.state.recoveryCodesList.length
        ) {
            await twoFactorAuth.fetchRecoveryCodes();
        }

        isRecoveryCodesVisible = !isRecoveryCodesVisible;

        if (isRecoveryCodesVisible) {
            await tick();
            recoveryCodeSectionRef?.scrollIntoView({ behavior: 'smooth' });
        }
    }

    onMount(async () => {
        if (!twoFactorAuth.state.recoveryCodesList.length) {
            await twoFactorAuth.fetchRecoveryCodes();
        }
    });
</script>

<Card class="w-full">
    <CardHeader>
        <CardTitle class="flex gap-3">
            <LockKeyhole class="size-4" />2FA-Wiederherstellungscodes
        </CardTitle>
        <CardDescription>
            Mit Wiederherstellungscodes erhalten Sie wieder Zugriff, falls Sie
            Ihr 2FA-Gerät verlieren. Bewahren Sie sie in einem sicheren
            Passwort-Manager auf.
        </CardDescription>
    </CardHeader>
    <CardContent>
        <div
            class="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between"
        >
            <Button onclick={toggleRecoveryCodesVisibility} class="w-fit">
                {#if isRecoveryCodesVisible}
                    <EyeOff class="size-4" />
                {:else}
                    <Eye class="size-4" />
                {/if}
                Wiederherstellungscodes {isRecoveryCodesVisible
                    ? 'ausblenden'
                    : 'anzeigen'}
            </Button>

            {#if isRecoveryCodesVisible && twoFactorAuth.state.recoveryCodesList.length}
                <Form
                    {...regenerateRecoveryCodes.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => twoFactorAuth.fetchRecoveryCodes()}
                >
                    {#snippet children({ processing })}
                        <Button
                            variant="secondary"
                            type="submit"
                            disabled={processing}
                        >
                            <RefreshCw class="size-4" /> Codes neu generieren
                        </Button>
                    {/snippet}
                </Form>
            {/if}
        </div>
        <div
            class="relative overflow-hidden transition-all duration-300 {isRecoveryCodesVisible
                ? 'h-auto opacity-100'
                : 'h-0 opacity-0'}"
        >
            {#if twoFactorAuth.state.errors.length}
                <div class="mt-6">
                    <AlertError errors={twoFactorAuth.state.errors} />
                </div>
            {:else}
                <div class="mt-3 space-y-3">
                    <div
                        bind:this={recoveryCodeSectionRef}
                        class="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                    >
                        {#if !twoFactorAuth.state.recoveryCodesList.length}
                            <div class="space-y-2">
                                {#each { length: 8 } as _, n (n)}
                                    <div
                                        class="h-4 animate-pulse rounded bg-muted-foreground/20"
                                    ></div>
                                {/each}
                            </div>
                        {:else}
                            {#each twoFactorAuth.state.recoveryCodesList as code, index (index)}
                                <div>{code}</div>
                            {/each}
                        {/if}
                    </div>
                    <p class="text-xs text-muted-foreground select-none">
                        Jeder Wiederherstellungscode kann einmal verwendet werden
                        und wird danach entfernt. Wenn Sie neue Codes benötigen,
                        klicken Sie oben auf
                        <span class="font-bold">Codes neu generieren</span>.
                    </p>
                </div>
            {/if}
        </div>
    </CardContent>
</Card>
