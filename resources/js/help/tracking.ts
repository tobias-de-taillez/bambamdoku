import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/svelte';
import { redactString } from './redact';

/**
 * Schneidet global mit, was eine Hilfe-Meldung braucht: die letzten JS-Fehler,
 * abgewiesenen Promises, fehlgeschlagenen Anfragen und Navigationen.
 */
export type LoggedError = {
    at: string;
    type: 'error' | 'unhandledrejection' | 'http' | 'network';
    message: string;
    source?: string;
    line?: number;
    column?: number;
    stack?: string;
};

export type LoggedNavigation = {
    at: string;
    url: string;
    component: string;
    type: string;
};

const LIMIT = 20;
const errors: LoggedError[] = [];
const navigations: LoggedNavigation[] = [];
let lastPage: Page | null = null;
let started = false;

function remember<T>(list: T[], entry: T): void {
    list.push(entry);

    if (list.length > LIMIT) {
        list.splice(0, list.length - LIMIT);
    }
}

function clip(value: unknown, max = 2000): string {
    const text = typeof value === 'string' ? value : String(value);

    return redactString(text.length > max ? `${text.slice(0, max)}…` : text);
}

function describeReason(reason: unknown): { message: string; stack?: string } {
    if (reason instanceof Error) {
        return {
            message: clip(reason.message),
            stack: clip(reason.stack ?? '', 4000),
        };
    }

    try {
        return { message: clip(JSON.stringify(reason)) };
    } catch {
        return { message: clip(reason) };
    }
}

/** Vor createInertiaApp aufrufen, damit auch die erste Seite und frühe Fehler ankommen. */
export function startHelpTracking(): void {
    if (started) {
        return;
    }

    started = true;

    window.addEventListener('error', (event) => {
        remember(errors, {
            at: new Date().toISOString(),
            type: 'error',
            message: clip(event.message || 'Fehler beim Laden einer Ressource'),
            source: event.filename ? clip(event.filename, 500) : undefined,
            line: event.lineno || undefined,
            column: event.colno || undefined,
            stack:
                event.error instanceof Error
                    ? clip(event.error.stack ?? '', 4000)
                    : undefined,
        });
    });

    window.addEventListener('unhandledrejection', (event) => {
        remember(errors, {
            at: new Date().toISOString(),
            type: 'unhandledrejection',
            ...describeReason(event.reason),
        });
    });

    router.on('navigate', (event) => {
        lastPage = event.detail.page;
        remember(navigations, {
            at: new Date().toISOString(),
            url: clip(event.detail.page.url, 500),
            component: event.detail.page.component,
            type: event.detail.type,
        });
    });

    router.on('httpException', (event) => {
        remember(errors, {
            at: new Date().toISOString(),
            type: 'http',
            message: `HTTP ${event.detail.response.status}`,
        });
    });

    router.on('networkError', (event) => {
        remember(errors, {
            at: new Date().toISOString(),
            type: 'network',
            ...describeReason(event.detail.error),
        });
    });
}

export function trackedPage(): Page | null {
    return lastPage;
}

export function trackedErrors(): LoggedError[] {
    return [...errors];
}

export function trackedNavigations(): LoggedNavigation[] {
    return [...navigations];
}
