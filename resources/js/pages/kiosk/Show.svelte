<script lang="ts">
    import { router, usePoll } from '@inertiajs/svelte';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import Delete from '@lucide/svelte/icons/delete';
    import LockKeyhole from '@lucide/svelte/icons/lock-keyhole';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import SelfEntryForm from '@/components/range-office/SelfEntryForm.svelte';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { store, verify } from '@/routes/kiosk';
    import type { LastEntry, Option } from '@/types/range-office';

    type KioskParticipant = {
        id: number;
        name: string;
        member_number: string | null;
        has_pin: boolean;
    };

    let {
        ended,
        token = '',
        supervisor = '',
        participants = [],
        canEnterLicensed = false,
        weaponClasses = [],
        disciplines = [],
    }: {
        ended: boolean;
        token?: string;
        supervisor?: string;
        participants?: KioskParticipant[];
        canEnterLicensed?: boolean;
        weaponClasses?: Option[];
        disciplines?: Option[];
    } = $props();

    // Neue Teilnehmer und das Ende der Session (Abzeichnen) nachladen
    usePoll(30000, { only: ['ended', 'participants'] });

    let step = $state<'list' | 'pin' | 'form' | 'done'>('list');
    let chosen = $state<KioskParticipant | null>(null);
    let pin = $state('');
    let pinError = $state<string | null>(null);
    let checking = $state(false);
    let last = $state<LastEntry | null>(null);
    let doneMessage = $state('');
    let doneTimer: ReturnType<typeof setTimeout> | undefined;

    function backToList() {
        clearTimeout(doneTimer);
        step = 'list';
        chosen = null;
        pin = '';
        pinError = null;
        last = null;
    }

    function choose(p: KioskParticipant) {
        if (!p.has_pin) {
            return;
        }

        chosen = p;
        pin = '';
        pinError = null;
        step = 'pin';
    }

    function press(digit: string) {
        if (pin.length < 6) {
            pin += digit;
        }
    }

    function checkPin() {
        if (!chosen || pin.length < 4) {
            return;
        }

        checking = true;
        router.post(
            toUrl(verify(token)),
            { participant_id: chosen.id, pin },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (
                        page as {
                            flash?: {
                                kiosk?: {
                                    participant_id: number;
                                    last: LastEntry | null;
                                };
                            };
                        }
                    ).flash?.kiosk;

                    if (flash && flash.participant_id === chosen?.id) {
                        last = flash.last;
                        step = 'form';
                    }
                },
                onError: (errors) => {
                    pinError = (errors as Record<string, string>).pin ?? null;
                    pin = '';
                },
                onFinish: () => (checking = false),
            },
        );
    }

    function nowHHMM(): string {
        const d = new Date();

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    const digits = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
</script>

<AppHead title="Kiosk" />

<main class="mx-auto flex min-h-svh max-w-2xl flex-col gap-6 bg-background p-4 text-foreground md:p-8">
    {#if ended}
        <section class="m-auto grid gap-3 text-center">
            <h1 class="text-3xl font-semibold">Session beendet</h1>
            <p class="text-lg text-muted-foreground">
                Die Aufsicht hat abgezeichnet, oder der Tag ist vorbei. Einträge sind
                hier nicht mehr möglich.
            </p>
        </section>
    {:else if step === 'done'}
        <section
            class="m-auto flex w-full flex-col items-center gap-4 rounded-2xl bg-emerald-600 p-8 text-center text-white"
            role="status"
        >
            <CircleCheck class="size-16" />
            <p class="text-2xl font-semibold">{doneMessage}</p>
            <p class="text-lg opacity-90">Zählt, sobald die Aufsicht abzeichnet.</p>
            <Button
                variant="secondary"
                class="h-14 px-8 text-lg"
                onclick={backToList}>Zurück zur Liste</Button
            >
        </section>
    {:else if step === 'form' && chosen}
        <header>
            <h1 class="text-2xl font-semibold">{chosen.name}</h1>
            <p class="text-muted-foreground">Ihr Eintrag für heute</p>
        </header>
        <SelfEntryForm
            action={toUrl(store(token))}
            {weaponClasses}
            {disciplines}
            {canEnterLicensed}
            {last}
            extra={{ participant_id: chosen.id, pin }}
            oncancel={backToList}
            onsuccess={(created) => {
                doneMessage = created
                    ? `${chosen?.name}: erfasst um ${nowHHMM()} Uhr`
                    : `${chosen?.name}: schon erfasst`;
                pin = '';
                step = 'done';
                doneTimer = setTimeout(backToList, 6000);
            }}
            onerror={(errors) => {
                if (errors.pin) {
                    pinError = errors.pin;
                    pin = '';
                    step = 'pin';
                }
            }}
        />
    {:else if step === 'pin' && chosen}
        <header>
            <h1 class="text-2xl font-semibold">{chosen.name}</h1>
            <p class="text-muted-foreground">Bitte Ihre Kiosk-PIN eingeben</p>
        </header>
        <form
            class="grid gap-4"
            onsubmit={(e) => {
                e.preventDefault();
                checkPin();
            }}
        >
            <label class="sr-only" for="kiosk-pin">PIN</label>
            <input
                id="kiosk-pin"
                type="password"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="off"
                maxlength="6"
                bind:value={pin}
                oninput={() => (pin = pin.replace(/\D/g, '').slice(0, 6))}
                class="h-16 rounded-lg border-2 border-input bg-background text-center text-3xl tracking-[0.5em]"
            />
            <InputError message={pinError ?? undefined} />
            <div class="grid grid-cols-3 gap-3" role="group" aria-label="Zifferntastatur">
                {#each digits as d (d)}
                    <Button
                        type="button"
                        variant="outline"
                        class="h-16 text-2xl"
                        onclick={() => press(d)}>{d}</Button
                    >
                {/each}
                <Button
                    type="button"
                    variant="ghost"
                    class="h-16"
                    onclick={backToList}>Abbrechen</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    class="h-16 text-2xl"
                    onclick={() => press('0')}>0</Button
                >
                <Button
                    type="button"
                    variant="ghost"
                    class="h-16"
                    aria-label="Letzte Ziffer löschen"
                    onclick={() => (pin = pin.slice(0, -1))}
                >
                    <Delete class="size-6" />
                </Button>
            </div>
            <Button
                type="submit"
                class="h-16 text-xl"
                disabled={checking || pin.length < 4}
            >
                {#if checking}<Spinner />{/if}
                Weiter
            </Button>
        </form>
    {:else}
        <header>
            <h1 class="text-2xl font-semibold">Selbst eintragen</h1>
            <p class="text-muted-foreground">
                Session von {supervisor}. Namen antippen und eigene PIN eingeben.
            </p>
        </header>
        {#if participants.length === 0}
            <p class="rounded-lg border p-6 text-center text-muted-foreground">
                Noch niemand aktiviert. Die Aufsicht aktiviert Sie vor Ort.
            </p>
        {:else}
            <ul class="grid gap-3">
                {#each participants as p (p.id)}
                    <li>
                        <button
                            type="button"
                            disabled={!p.has_pin}
                            class="flex min-h-16 w-full items-center justify-between gap-3 rounded-xl border-2 px-4 text-left text-lg transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
                            onclick={() => choose(p)}
                        >
                            <span class="font-medium">{p.name}</span>
                            <span class="text-right text-sm text-muted-foreground">
                                {#if p.has_pin}
                                    {p.member_number ? `Nr. ${p.member_number}` : ''}
                                {:else}
                                    <span class="inline-flex items-center gap-1"
                                        ><LockKeyhole class="size-4" /> bitte bei der Aufsicht
                                        eintragen</span
                                    >
                                {/if}
                            </span>
                        </button>
                    </li>
                {/each}
            </ul>
        {/if}
        <p class="mt-auto text-sm text-muted-foreground">
            Gäste und Mitglieder ohne PIN trägt die Aufsicht ein. Die PIN setzen Sie in
            Ihrem Konto unter Einstellungen → Sicherheit.
        </p>
    {/if}
</main>
