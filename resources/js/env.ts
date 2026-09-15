export interface IzboriEnv {
    dataUrl: string;
    siteName: string;
    publisher: string;
    methodologyUrl: string;
    pollSeconds: number;
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
    };
}

export const env: IzboriEnv = readEnv();
