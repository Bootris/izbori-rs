export function Loading({ label = 'Učitavam…' }: { label?: string }) {
    return (
        <div className="flex items-center gap-3 py-10 text-zinc-500" role="status" aria-live="polite">
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-zinc-300 border-t-primary" />
            <span className="text-sm">{label}</span>
        </div>
    );
}

export function ErrorBox({ title, detail }: { title: string; detail?: string }) {
    return (
        <div className="card border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">
            <p className="font-semibold">{title}</p>
            {detail && <p className="mt-1 text-sm">{detail}</p>}
        </div>
    );
}

export function Empty({ text }: { text: string }) {
    return <p className="muted py-6 text-center">{text}</p>;
}
