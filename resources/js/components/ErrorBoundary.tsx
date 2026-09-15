import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props { children: ReactNode }
interface State { error: Error | null }

/**
 * A render error must never leave a blank page on election night. A failed
 * dynamic import (the usual cause: a new version was published while the tab
 * was open) gets its own message, because reloading fixes exactly that.
 */
export class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        // Keeps the stack in the browser console for whoever is on duty.
        console.error('Greška pri prikazu stranice', error, info.componentStack);
    }

    render(): ReactNode {
        const { error } = this.state;
        if (!error) return this.props.children;

        const stale = /dynamically imported module|Importing a module script failed|Loading chunk/i.test(error.message);

        return (
            <div className="container-x py-10">
                <div className="mx-auto max-w-xl rounded-xl border border-line bg-white p-6 text-center" role="alert">
                    <h1 className="mb-2">{stale ? 'Objavljena je nova verzija' : 'Stranica se ne može prikazati'}</h1>
                    <p className="muted mb-5">
                        {stale
                            ? 'Sajt je u međuvremenu objavio novu verziju podataka. Osvežite stranicu da nastavite.'
                            : 'Došlo je do greške pri prikazu. Osvežite stranicu; ako se ponovi, podaci su i dalje dostupni kao JSON fajlovi.'}
                    </p>
                    <button type="button" className="btn-primary" onClick={() => window.location.reload()}>Osveži stranicu</button>
                </div>
            </div>
        );
    }
}
