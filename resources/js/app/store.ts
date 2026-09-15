import { configureStore } from '@reduxjs/toolkit';
import { dataApi } from './api';
import { uiSlice } from './ui-slice';

export const store = configureStore({
    reducer: {
        [dataApi.reducerPath]: dataApi.reducer,
        ui: uiSlice.reducer,
    },
    middleware: (getDefault) => getDefault().concat(dataApi.middleware),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
