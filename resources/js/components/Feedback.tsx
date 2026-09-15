export function Loading({ label = 'Učitavam...' }: { label?: string }) {
    return (
        <div className="flex items-center gap-3 py-10 text-ink-3" role="status" aria-live="polite">
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-line border-t-primary" />
            <span className="text-sm">{label}</span>
        </div>
    );
}

export function ErrorBox({ title, detail }: { title: string; detail?: string }) {
    return (
        <div className="rounded-xl border border-red-200 bg-bad-soft p-4 text-red-900" role="alert">
            <p className="font-semibold">{title}</p>
            {detail && <p className="mt-1 text-sm">{detail}</p>}
        </div>
    );
}

export function Empty({ text }: { text: string }) {
    return <p className="muted py-6 text-center">{text}</p>;
}
