/* Types mirroring docs/DATA-CONTRACT.md. Every published file is an envelope: { meta, list } or { meta, data }. */

export type Source = 'registry' | 'turnout' | 'results';
export type ElectionType = 'parliamentary' | 'provincial' | 'local' | 'presidential';
export type ElectionStatus = 'draft' | 'registry' | 'voting' | 'counting' | 'final';
export type AllocationMethod = 'dhondt' | 'majority_runoff';
export type ProtocolStatus = 'entered' | 'flagged' | 'verified' | 'annulled';

export interface SourceVersions {
    registry: string | null;
    turnout: string | null;
    results: string | null;
}

export interface IndexElection {
    slug: string;
    name: string;
    type: ElectionType;
    election_date: string;
    status: ElectionStatus;
    round: number;
    sources: SourceVersions;
}

export interface SiteInfo {
    name: string;
    publisher: string | null;
    notice: string | null;
    contact_email: string | null;
    methodology_url: string | null;
}

export interface IndexFile {
    generated: string;
    site?: SiteInfo | null;
    default: string | null;
    elections: IndexElection[];
}

export type ElectionSummary = IndexElection;

export interface ElectionConfig extends SourceVersions {
    election: string;
    updated: string;
}

export type ConfigFile = ElectionConfig;

export interface Meta {
    generated: string;
    electionDate: string;
    electionId: number;
    electionSlug: string;
    electionType: ElectionType;
    round: number;
    source: Source;
    version: string;
    processed: number | null;
}

export interface ListFile<T> {
    meta: Meta;
    list: T[];
}

export interface DataFile<T> {
    meta: Meta;
    data: T;
}

/* ---------- registry ---------- */

export interface ElectionInfo {
    id: number;
    slug: string;
    name: string;
    type: ElectionType;
    election_date: string;
    round: number;
    rounds: number;
    allocation: AllocationMethod;
    seats: number | null;
    threshold_pct: number | null;
    minority_coef: number | null;
    status: ElectionStatus;
    description: string | null;
    counts: {
        units: number;
        districts: number;
        municipalities: number;
        stations: number;
        registered_voters: number;
        lists: number;
        candidates: number;
    };
}

export interface CodebookEntry {
    table: string;
    code: string;
    label: string;
}

export interface District {
    code: string;
    name: string;
    municipalities: number;
    stations: number;
    registered_voters: number;
}

export interface Municipality {
    code: string;
    name: string;
    district_code: string;
    unit_codes: string[];
    stations: number;
    registered_voters: number;
}

export interface Unit {
    code: string;
    name: string;
    seats: number | null;
    municipality_codes: string[];
    stations: number;
    registered_voters: number;
}

export interface Submitter {
    id: number;
    name: string;
    short_name: string | null;
    type: string;
    is_minority: boolean;
    color: string | null;
}

export interface Candidate {
    position: number;
    full_name: string;
    birth_year: number | null;
    occupation: string | null;
    residence: string | null;
    gender: string | null;
}

export interface ElectoralList {
    id: number;
    unit_code: string;
    number: number;
    name: string;
    short_name: string | null;
    holder_name: string | null;
    is_minority: boolean;
    color: string | null;
    submitter_id: number | null;
    candidates: Candidate[];
}

export interface Deadline {
    date: string;
    title: string;
    description: string | null;
    legal_basis: string | null;
}

export interface Station {
    station_id: string;
    number: string;
    name: string;
    address: string | null;
    municipality_code: string;
    district_code: string;
    registered_voters: number;
    accessible: boolean;
    is_diaspora: boolean;
    country: string | null;
    lat: number | null;
    lng: number | null;
}

/* ---------- turnout ---------- */

export interface TurnoutCutoff {
    cutoff: string;
    registered_voters: number;
    voters_voted: number | null;
    turnout_pct: number | null;
    municipalities_reported: number;
    municipalities_total: number;
}

export interface DistrictTurnout {
    district_code: string;
    registered_voters: number;
    cutoffs: TurnoutCutoff[];
}

export interface MunicipalityCutoff {
    cutoff: string;
    voters_voted: number | null;
    turnout_pct: number | null;
}

export interface MunicipalityTurnout {
    code: string;
    name: string;
    district_code: string;
    registered_voters: number;
    cutoffs: MunicipalityCutoff[];
}

/* ---------- results ---------- */

export interface Totals {
    stations_total: number;
    stations_verified: number;
    stations_entered: number;
    stations_flagged: number;
    processed: number;
    registered_voters_all: number;
    registered_voters: number;
    voters_voted: number;
    turnout_pct: number;
    ballots_in_box: number;
    ballots_valid: number;
    ballots_invalid: number;
    invalid_pct: number;
}

export interface ListRow {
    list_id: number;
    number: number;
    name: string;
    short_name: string | null;
    holder_name: string | null;
    is_minority: boolean;
    color: string | null;
    submitter_id: number | null;
    votes: number;
    votes_pct: number;
}

export interface UnitListRow extends ListRow {
    seats: number;
    qualified: boolean;
}

export interface UnitAllocation {
    threshold_votes: number;
    notes: string[];
    winner: number | string | null;
    runoff: Array<number | string>;
}

export interface UnitSummary extends Totals {
    code: string;
    name: string;
    seats: number | null;
    lists: UnitListRow[];
    allocation: UnitAllocation;
}

export interface ResultsSummary extends Totals {
    round: number;
    units: UnitSummary[];
}

export interface SeatCandidate {
    position: number;
    full_name: string;
    birth_year: number | null;
    occupation: string | null;
    residence: string | null;
}

export interface Seat {
    seat_no: number;
    list_id: number;
    list_number: number;
    list_short_name: string;
    color: string | null;
    divisor: number;
    quotient: number;
    candidate: SeatCandidate | null;
}

export interface SeatOrderEntry {
    seat_no: number;
    list_id: number;
    divisor: number;
    quotient: number;
}

/** matrix[list_id][divisor] = quotient (keys are strings because of JSON) */
export type DHondtMatrix = Record<string, Record<string, number>>;

export interface UnitResults extends UnitSummary {
    matrix: DHondtMatrix;
    seat_order: SeatOrderEntry[];
    seat_rows: Seat[];
}

export interface CompositionByList {
    name: string;
    short_name: string | null;
    color: string | null;
    is_minority: boolean;
    seats: number;
    votes: number;
    list_ids: number[];
    seats_pct: number;
}

export interface CompositionSeat extends Seat {
    unit_code: string;
}

export interface Composition {
    seats_total: number;
    seats_allocated: number;
    seats_empty: number;
    by_list: CompositionByList[];
    seats: CompositionSeat[];
}

export interface Winner {
    unit_code: string;
    unit_name: string;
    processed: number;
    leader: { list_id: number; name: string; votes: number; votes_pct: number; seats: number } | null;
    margin_pct: number | null;
    winner: number | string | null;
    runoff: Array<number | string>;
}

export type CloseRace =
    | { type: 'threshold'; unit_code: string; list_id: number; name: string; margin_pct: number }
    | { type: 'first_second'; unit_code: string; list_ids: number[]; names: string[]; margin_pct: number };

export interface FlaggedStation {
    station_id: string;
    station_name: string;
    municipality_code: string;
    municipality_name: string;
    district_code: string;
    deviation: number;
    errors: Record<string, string>;
    revision: number;
}

export interface DistrictResults extends Totals {
    district_code: string;
    name: string;
    unit_code: string | null;
    lists: ListRow[];
}

export interface MunicipalityResults extends Totals {
    code: string;
    name: string;
    district_code: string;
    unit_code: string | null;
    lists: ListRow[];
}

export interface ProtocolItem {
    list_id: number;
    votes: number;
    votes_pct: number;
}

export interface ProtocolFields {
    revision: number;
    recount_requested: boolean;
    registered_voters_protocol: number;
    ballots_received: number;
    ballots_unused: number;
    voters_voted: number;
    turnout_pct: number;
    ballots_in_box: number;
    ballots_valid: number;
    ballots_invalid: number;
    deviation: number;
    errors: Record<string, string> | null;
    verified_at: string | null;
    items: ProtocolItem[];
    scans: string[];
}

/** Row of protocols-{d}-{m}.json: the protocol fields are absent while no protocol has been entered. */
export type StationProtocol = Station & { status?: ProtocolStatus | null } & Partial<ProtocolFields>;
export type EnteredProtocol = Station & { status?: ProtocolStatus | null } & ProtocolFields;
