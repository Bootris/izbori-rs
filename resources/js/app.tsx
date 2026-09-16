import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { BrowserRouter, HashRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { store } from '@/app/store';
import { env } from '@/env';
import { useIndexQuery } from '@/app/api';
import { ElectionProvider } from '@/app/election-context';
import { Layout } from '@/components/Layout';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { ErrorBox, Loading } from '@/components/Feedback';
import { Home } from '@/pages/Home';
import { Composition } from '@/pages/Composition';
import { ListDetail, Lists } from '@/pages/Lists';
import { Submitter, Submitters } from '@/pages/Submitters';
import { Turnout } from '@/pages/Turnout';
import { Territory, TerritoryDistrict, TerritoryMunicipality } from '@/pages/Territory';
import { Station } from '@/pages/Station';
import { Mandates } from '@/pages/Mandates';
import { Protocols } from '@/pages/Protocols';
import { About, Deadlines, NotFound } from '@/pages/Misc';
import { Incidents, InfoHub, Voters } from '@/pages/Info';

/** "/" redirects to the election index.json marks as default. */
function RootRedirect() {
    const index = useIndexQuery();
    if (index.isLoading) return <Loading label="Učitavam..." />;
    if (index.error || !index.data?.default) {
        return <div className="mx-auto max-w-2xl p-6"><ErrorBox title="Nema objavljenih izbora" detail="index.json ne postoji ili ne sadrži nijedan izbor. Objavite registar iz admina (izbori:publish)." /></div>;
    }
    return <Navigate to={`/${index.data.default}`} replace />;
}

/** Keeps older or descriptive URLs working instead of showing a 404. */
function Alias({ to }: { to: string }) {
    const { election = '' } = useParams();
    return <Navigate to={`/${election}/${to}`} replace />;
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
                <Route path="podnosioci" element={<Submitters />} />
                <Route path="podnosioci/:submitterId" element={<Submitter />} />
                <Route path="izlaznost" element={<Turnout />} />
                <Route path="teritorija" element={<Territory />} />
                <Route path="teritorija/:district" element={<TerritoryDistrict />} />
                <Route path="teritorija/:district/:municipality" element={<TerritoryMunicipality />} />
                <Route path="biracko-mesto/:stationId" element={<Station />} />
                <Route path="mandati" element={<Mandates />} />
                <Route path="zapisnici" element={<Protocols />} />
                <Route path="rokovi" element={<Deadlines />} />
                <Route path="o-podacima" element={<About />} />
                <Route path="informacije" element={<InfoHub />} />
                <Route path="vanredni-dogadjaji" element={<Incidents />} />
                <Route path="biraci" element={<Voters />} />
                <Route path="raspodela-mandata" element={<Alias to="mandati" />} />
                <Route path="sastav-skupstine" element={<Alias to="skupstina" />} />
                <Route path="izborne-liste" element={<Alias to="liste" />} />
                <Route path="po-teritoriji" element={<Alias to="teritorija" />} />
                <Route path="prijave" element={<Alias to="vanredni-dogadjaji" />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}

const Router = env.hashRouting ? HashRouter : BrowserRouter;

const root = document.getElementById('root');
if (root) {
    createRoot(root).render(
        <StrictMode>
            <Provider store={store}>
                <ErrorBoundary>
                    <Router>
                        <App />
                    </Router>
                </ErrorBoundary>
            </Provider>
        </StrictMode>,
    );
}
