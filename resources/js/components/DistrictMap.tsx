import { lazy, Suspense } from 'react';
import type { DistrictMapProps } from './DistrictMapInner';
import { Loading } from './Feedback';

const Inner = lazy(() => import('./DistrictMapInner'));

/** The district shapes ship in their own chunk, loaded only where the map is shown. */
export function DistrictMap(props: DistrictMapProps) {
    return (
        <Suspense fallback={<Loading label="Učitavam mapu..." />}>
            <Inner {...props} />
        </Suspense>
    );
}
