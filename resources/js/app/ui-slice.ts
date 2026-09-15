import { createSlice, type PayloadAction } from '@reduxjs/toolkit';

export type Script = 'lat' | 'cyr';

const KEY = 'izbori.script';

function initialScript(): Script {
    try {
        return localStorage.getItem(KEY) === 'cyr' ? 'cyr' : 'lat';
    } catch {
        return 'lat';
    }
}

export const uiSlice = createSlice({
    name: 'ui',
    initialState: { script: initialScript() as Script },
    reducers: {
        setScript(state, action: PayloadAction<Script>) {
            state.script = action.payload;
            try {
                localStorage.setItem(KEY, action.payload);
            } catch {
                // private mode / blocked storage — the toggle still works for this page view
            }
        },
    },
});

export const { setScript } = uiSlice.actions;
