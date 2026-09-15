import type { ReactNode } from 'react';
import { Empty, ErrorBox, Loading } from './Feedback';

interface FileState<T> {
    data: T | undefined;
    isLoading: boolean;
    error: unknown;
    available: boolean;
}

/** Uniform loading / not-yet-published / error handling around one snapshot file. */
export function WithFile<T>({ state, unavailable, children }: { state: FileState<T>; unavailable: string; children: (data: T) => ReactNode }) {
    if (!state.available) return <Empty text={unavailable} />;
    if (state.isLoading) return <Loading />;
    if (state.error || state.data === undefined) return <ErrorBox title="Fajl se ne može učitati" detail="Pokušajte ponovo za minut, objava je možda u toku." />;
    return <>{children(state.data)}</>;
}
