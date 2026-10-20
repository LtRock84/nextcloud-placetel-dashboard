import type { Agent } from '../shared/types';

const statusOrder: Record<string, number> = { online: 0, working: 1, dail_out: 2, dial_out: 2, pause: 3, offline: 5 };
const names = new Intl.Collator('de', { sensitivity: 'base', numeric: true });

export function sortAgents(agents: Agent[]): Agent[] {
  return [...agents].sort((a, b) =>
    (statusOrder[a.status] ?? 4) - (statusOrder[b.status] ?? 4)
    || names.compare(a.name, b.name)
    || names.compare(a.id, b.id));
}
