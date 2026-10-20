export const ZONE = 'Europe/Berlin';
export type Mode = 'demo' | 'live';
export type Agent = { id: string; name: string; description?: string; status: string; color?: string; priority?: number };
export type Queue = { id: string; name: string; description?: string; priority?: number; ringing_time?: number; num_loops?: number; max_iterations?: number };
export type Assignment = { queueId: string; skill: number };
export type Plan = {
  id: string; agentId: string; label: string; kind: 'weekly' | 'once';
  weekdays: number[]; date: string; start: string; end: string;
  assignments: Assignment[]; enabled: boolean; createdAt: string;
};
export type CallSegment = {
  id: string; rootId: string; agentId: string; queueId: string; caller: string | null;
  offeredAt: string; acceptedAt: string | null; endedAt: string | null;
  outcome: 'accepted' | 'missed' | 'waiting' | 'unknown'; durationVerified: boolean;
  referenceAt?: string; durationStart?: string | null; source?: 'placetel'; agentName?: string; queueName?: string;
  callType?: string; callStatus?: string; answerStatus?: string; iteration?: string; updatedAt?: string;
};
export type Coverage = { date: string; status: 'complete' | 'imported' | 'unverified' | 'error'; updatedAt: string; message?: string };
export type Audit = { id: number; at: string; action: string; agentId?: string; before?: string; after?: string; status: 'ok' | 'error' | 'info'; message: string };
export type Status = {
  mode: Mode; connected: boolean; account: string | null; syncing: boolean;
  lastSync: string | null; lastAttempt: string | null; error: string | null;
  nextRequestAt: string | null; historyProgress: string | null;
  lastCallsSync?: string | null;
  capabilities: { assignments: boolean; verifiedStatistics: boolean; liveCalls: boolean; callStatistics?: boolean; agentManagement?: boolean; queueManagement?: boolean };
};
export type Bootstrap = {
  hosting?: {native?:boolean;managedSecrets:boolean;configured?:boolean;role:string;user:string};
  access?: {operator_groups?:string[];viewer_groups?:string[]}|null;
  operations?: OperationsState;
  dispolive?: DispoliveState;
  network?: { lan: boolean; urls: string[] };
  status: Status; agents: Agent[]; queues: Queue[]; plans: Plan[];
  assignments: Record<string, Assignment[]>; overrides: Record<string, string>;
  audit: Audit[];
  queueSnapshot?: QueueSnapshot;
};
export type QueueCaller = { id: string; caller: string | null; enteredAt: string; agentName: string | null; phase: 'waiting' | 'assigned' };
export type RecentQueueCall = { id: string; caller: string | null; at: string; timeBasis: 'accepted' | 'queue-exit' | 'started'; agentName: string | null; endedAt: string | null };
export type QueueSnapshot = {
  asOf: string | null; serverNow: string; fresh: boolean; refreshIntervalSeconds: number; unmappedWaiting: number;
  queues: { id: string; waiting: number; active: number; longestWaitSeconds: number | null; waitingCallers: QueueCaller[]; recentAccepted: RecentQueueCall[] }[];
};
export type Bucket = { key: string; label: string; accepted: number; minutes: number };
export type Statistics = {
  from: string; to: string; asOf: string; accepted: number; uniqueCallers: number;
  anonymousCalls: number; durationSeconds: number; averageSeconds: number | null;
  durationKnown: number; completed: number; ongoing: number; waiting: number;
  estimatedDurationSeconds?: number; estimatedAverageSeconds?: number | null; estimateKnown?: number;
  timeBasis?: 'accepted' | 'started'; durationBasis?: 'verified' | 'queue-exit-estimate';
  failedAttempts?: number; unmappedAgents?: number; unmappedQueues?: number;
  coverage: { complete: number; imported?: number; expected: number; missing: string[]; unverified: string[] };
  hourly: Bucket[]; timeOfDay: Bucket[];
  calls: (CallSegment & { normalizedCaller: string | null; seconds: number | null; estimatedSeconds?: number | null })[];
};

export type AlertSettings = { warningSeconds: number; criticalSeconds: number; sound: boolean };
export type CallbackState = 'open' | 'done' | 'later';
export type CallbackTask = { key: string; caller: string | null; queueId: string; lastAt: string; attempts: number; latestId: string; state: CallbackState; note: string; laterAcceptedAt: string | null };
export type Reachability = { queueId: string; accepted: number; failedAttempts: number; cancelledAttempts: number; uniqueFailed: number; unknownCallerAttempts: number };
export type OperationsReport = { from: string; to: string; asOf: string; callbacks: CallbackTask[]; reachability: Reachability[]; coverage: Statistics['coverage'] };
export type RoutingProfile = { id: string; label: string; routingId: string; planId: string; activateAt: string | null; state: 'ready' | 'pending' | 'executing' | 'done' | 'error' | 'skipped'; message?: string };
export type NotifyCall = { id: string; caller: string | null; to: string; peer: string | null; direction: 'in' | 'out'; firstReceivedAt: string; lastReceivedAt: string; events: string[]; durationSeconds: number | null; hangupType: string | null };
export type OperationsState = {
  alerts: AlertSettings; contactNames: Record<string, string>; contactsAt: string | null; contactsError: string | null;
  routing: { routes: {id: string; name: string}[]; plans: {id: string; name: string}[]; profiles: RoutingProfile[]; loadedAt: string | null };
  notify: { configured: boolean; lastReceivedAt: string | null; calls: NotifyCall[]; total: number };
};

export type DispoliveContact = { id: string | null; name: string; type: string };
export type DispoliveMatch = { number: string; contacts: DispoliveContact[]; status: 'matched' | 'none' | 'error'; checkedAt: string; expiresAt: string; message?: string };
export type DispoliveState = { popups?:DispolivePopupState; origin: string; version: string; configured: boolean; enabled: boolean; connected: boolean; error: string | null; lastSuccess: string | null; pending: number; matches: Record<string, DispoliveMatch> };

export type DispolivePopupState = {enabled:boolean;desktopPhone:string;firmenKrz:string;calledNumbers:string[];pending:number;workerLastSeen:string|null;recent:{id:string;state:string;receivedAt:string;updatedAt:string;attempts:number;message:string}[]};
