import { createContext, useContext, type ReactNode } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import { CONFIG_POLL_MS, INDEX_POLL_MS, useConfigQuery, useIndexQuery } from './api';
import type { ConfigFile, ElectionSummary, IndexFile, SiteInfo } from '@/types';
import { ErrorBox, Loading } from '@/components/Feedback';

export interface ElectionContextValue {
    slug: string;
    election: ElectionSummary;
    config: ConfigFile;
    index: IndexFile;
    site: SiteInfo | null;
}

const Ctx = createContext<ElectionContextValue | null>(null);

export function useElection(): ElectionContextValue {
    const value = useContext(Ctx);
    if (!value) {
        throw new Error('useElection() outside <ElectionProvider>');
    }
    return value;
}

/**
 * Loads index.json + {election}/config.json and keeps polling them. Every
 * snapshot hook below derives its file URL from the version in config, so a
 * new publish changes the cache key and the page refetches by itself.
 */
export function ElectionProvider({ children }: { children: ReactNode }) {
    const { election: slug = '' } = useParams();
    const index = useIndexQuery(undefined, { pollingInterval: INDEX_POLL_MS });
    const config = useConfigQuery(slug, { pollingInterval: CONFIG_POLL_MS, skip: !slug });

    if (index.isLoading || config.isLoading) {
        return <Loading label="Učitavam podatke…" />;
    }
    if (index.error || !index.data) {
        return <ErrorBox title="Podaci nisu dostupni" detail="index.json se ne može učitati. Ako je sajt tek postavljen, još nijedan snapshot nije objavljen." />;
    }
    const election = index.data.elections.find((e) => e.slug === slug);
    if (!election) {
        return index.data.default ? <Navigate to={`/${index.data.default}`} replace /> : <ErrorBox title="Nepoznat izbor" detail={`Nema objavljenih podataka za „${slug}".`} />;
    }
    if (config.error || !config.data) {
        return <ErrorBox title="config.json nije dostupan" detail="Pointer na objavljene verzije se ne može učitati." />;
    }

    return <Ctx.Provider value={{ slug, election, config: config.data, index: index.data, site: index.data.site ?? null }}>{children}</Ctx.Provider>;
}
