import type { ListRow } from '@/types';

/** Strongest list of a territory, ignoring lists that have no votes counted yet. */
export function leaderOf(lists: ListRow[]): ListRow | undefined {
    let best: ListRow | undefined;
    for (const row of lists) {
        if (row.votes > 0 && (best === undefined || row.votes > best.votes)) best = row;
    }
    return best;
}
