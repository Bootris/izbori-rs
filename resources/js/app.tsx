import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { store } from '@/app/store';
import { useIndexQuery } from '@/app/api';
import { ElectionProvider } from '@/app/election-context';
import { Layout } from '@/components/Layout';
import { ErrorBox, Loading } from '@/components/Feedback';
import { Home } from '@/pages/Home';
import { Composition } from '@/pages/Composition';
import { ListDetail, Lists } from '@/pages/Lists';
import { Turnout } from '@/pages/Turnout';
import { Territory, TerritoryDistrict, TerritoryMunicipality } from '@/pages/Territory';
import { Station } from '@/pages/Station';
import { Mandates } from '@/pages/Mandates';
import { Protocols } from '@/pages/Protocols';
import { About, Deadlines, NotFound } from '@/pages/Misc';

/** "/" → the election index.json marks as default. */
function RootRedirect() {
    const index = useIndexQuery();
    if (index.isLoading) return <Loading label="Učitavam…" />;
    if (index.error || !index.data?.default) {
        return <div className="mx-auto max-w-2xl p-6"><ErrorBox title="Nema objavljenih izbora" detail="index.json ne postoji ili ne sadrži nijedan izbor. Objavite registar iz admina (izbori:publish)." /></div>;
    }
    return <Navigate to={`/${index.data.default}`} replace />;
}

function App() {
    return (
        <Routes>
            <Route path="/" element={<RootRedirect />} />
            <Route path="/:election" element={<ElectionProvider><Layout /></ElectionProvider>}>
                <Route index element={<Home />} />
                <Route path="skupstina" element={<Composition />} />
                <Route path="liste" element={<Lists />} />
                <Route path="liste/:listId" element={<ListDetail />} />
                <Route path="izlaznost" element={<Turnout />} />
                <Route path="teritorija" element={<Territory />} />
                <Route path="teritorija/:district" element={<TerritoryDistrict />} />
                <Route path="teritorija/:district/:municipality" element={<TerritoryMunicipality />} />
                <Route path="biracko-mesto/:stationId" element={<Station />} />
                <Route path="mandati" element={<Mandates />} />
                <Route path="zapisnici" element={<Protocols />} />
                <Route path="rokovi" element={<Deadlines />} />
                <Route path="o-podacima" element={<About />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}

const root = document.getElementById('root');
if (root) {
    createRoot(root).render(
        <StrictMode>
            <Provider store={store}>
                <BrowserRouter>
                    <App />
                </BrowserRouter>
            </Provider>
        </StrictMode>,
    );
}
