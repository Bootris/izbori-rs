export interface IzboriEnv {
    dataUrl: string;
    siteName: string;
    publisher: string;
    methodologyUrl: string;
    pollSeconds: number;
    /**
     * Route in the URL fragment instead of the path. Set it when the snapshot
     * set is served straight from object storage, where there is no rewrite
     * rule to hand every path back to index.html.
     */
    hashRouting: boolean;
}

declare global {
    interface Window {
        __IZBORI__?: Partial<IzboriEnv>;
    }
}

const DEFAULTS: IzboriEnv = {
    dataUrl: '/data',
    siteName: 'IZBORI.RS',
    publisher: '',
    methodologyUrl: '',
    pollSeconds: 60,
    hashRouting: false,
};

function readEnv(): IzboriEnv {
    if (typeof window === 'undefined') return DEFAULTS;
    const raw = window.__IZBORI__ ?? {};
    const dataUrl = (raw.dataUrl ?? '').replace(/\/+$/, '');
    return {
        dataUrl: dataUrl === '' ? DEFAULTS.dataUrl : dataUrl,
        siteName: raw.siteName?.trim() || DEFAULTS.siteName,
        publisher: raw.publisher ?? '',
        methodologyUrl: raw.methodologyUrl ?? '',
        pollSeconds:
            typeof raw.pollSeconds === 'number' && raw.pollSeconds > 0 ? raw.pollSeconds : DEFAULTS.pollSeconds,
        hashRouting: raw.hashRouting === true,
    };
}

export const env: IzboriEnv = readEnv();
