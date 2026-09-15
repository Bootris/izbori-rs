import { useMemo } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { dataApi } from './api';
import type { AppDispatch, RootState } from './store';
import { useElection } from './election-context';
import { transliterate } from '@/lib/translit';
import type { DataFile, ListFile, Meta, Source } from '@/types';

export const useAppDispatch = () => useDispatch<AppDispatch>();
export const useAppSelector = <T,>(selector: (s: RootState) => T) => useSelector(selector);

interface SnapshotState<T> { data: T | undefined; meta: Meta | undefined; isLoading: boolean; error: unknown; available: boolean }

/** One published file of the current election. `available` is false while the source has never been published. */
export function useSnapshotFile<T>(source: Source, path: string, opts: { skip?: boolean } = {}): SnapshotState<T> {
    const { slug, config } = useElection();
    const version = config[source];
    const result = dataApi.useFileQuery(
        { election: slug, version: version ?? '', source, path },
        { skip: opts.skip || !version },
    );
    const file = result.data as { meta: Meta } | undefined;

    return {
        data: result.data as T | undefined,
        meta: file?.meta,
        isLoading: result.isLoading || (result.isFetching && !result.data),
        error: result.error,
        available: version !== null,
    };
}

export function useSnapshotList<T>(source: Source, path: string, opts?: { skip?: boolean }) {
    const s = useSnapshotFile<ListFile<T>>(source, path, opts);
    return { ...s, list: s.data?.list };
}

export function useSnapshotData<T>(source: Source, path: string, opts?: { skip?: boolean }) {
    const s = useSnapshotFile<DataFile<T>>(source, path, opts);
    return { ...s, item: s.data?.data };
}

/** Text helper: returns strings as-is (Latin) or transliterated to Cyrillic, per the user's toggle. */
export function useT(): (s: string | null | undefined) => string {
    const script = useAppSelector((s) => s.ui.script);
    return useMemo(() => (s: string | null | undefined) => (s == null ? '' : script === 'cyr' ? transliterate(s) : s), [script]);
}
