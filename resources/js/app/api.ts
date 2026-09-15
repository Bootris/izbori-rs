import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import { env } from '@/env';
import type { ConfigFile, IndexFile, Source } from '@/types';

/*
 * The SPA only ever reads static JSON under env.dataUrl:
 *   index.json and {election}/config.json are mutable  → never cached, polled
 *   {election}/{version}/{source}/{path} is immutable  → cached by its full args
 * When config.json reports a new version the file args change, so RTK Query
 * refetches the snapshot by itself.
 */
const uncached = (path: string) => ({ url: path, cache: 'no-cache' as RequestCache, params: { t: Date.now() } });

export interface FileArgs {
    election: string;
    version: string;
    source: Source;
    path: string;
}

export const dataApi = createApi({
    reducerPath: 'data',
    baseQuery: fetchBaseQuery({ baseUrl: `${env.dataUrl}/` }),
    keepUnusedDataFor: 600,
    endpoints: (build) => ({
        index: build.query<IndexFile, void>({
            query: () => uncached('index.json'),
        }),
        config: build.query<ConfigFile, string>({
            query: (election) => uncached(`${encodeURIComponent(election)}/config.json`),
        }),
        file: build.query<unknown, FileArgs>({
            query: ({ election, version, source, path }) => `${encodeURIComponent(election)}/${version}/${source}/${path}`,
        }),
    }),
});

export const { useIndexQuery, useConfigQuery } = dataApi;

/** Poll cadence for the two mutable pointer files (ms). */
export const CONFIG_POLL_MS = env.pollSeconds * 1000;
export const INDEX_POLL_MS = env.pollSeconds * 3000;
