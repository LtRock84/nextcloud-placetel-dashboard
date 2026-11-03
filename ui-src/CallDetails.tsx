import { useMemo, useState } from 'react';
import { DateTime } from 'luxon';
import { CircleHelp } from 'lucide-react';
import { Caller } from './OperationsPanels';
import { ZONE, type Agent, type Queue, type Statistics, type OperationsState } from '../shared/types';

type Detail = NonNullable<Statistics['detailCalls']>[number];
type Filter = 'all' | 'incoming' | 'outgoing' | 'failed';
export function selectDetails(calls: Detail[], filter: Filter): Detail[] {
  return calls.filter(c => filter === 'all' || (filter === 'incoming' && c.callType !== 'dail_out') || (filter === 'outgoing' && c.callType === 'dail_out') || (filter === 'failed' && c.outcome === 'missed'));
}
const direction = (c: Detail) => c.callType === 'dail_out' ? 'Ausgehend' : 'Eingehend';
const result = (c: Detail) => c.outcome === 'accepted' ? 'Beantwortet' : c.outcome === 'missed' ? 'Nicht angenommen / Versuch' : c.outcome === 'waiting' ? 'In Zustellung' : 'Unbekannt';
const timestamp = (iso: string | null) => iso ? DateTime.fromISO(iso).setZone(ZONE).toFormat('dd.MM. HH:mm:ss') : '—';
const duration = (seconds: number | null) => seconds === null ? '—' : `${Math.floor(Math.round(seconds) / 60)}:${String(Math.round(seconds) % 60).padStart(2,'0')}`;

export function CallDetails({stats, agents, queues, names, verified, approximate, notify, from, to}: {stats: Statistics | null; agents: Agent[]; queues: Queue[]; names: Record<string,string>; verified: boolean; approximate: boolean; notify?: OperationsState['notify']; from: string; to: string}) {
  const [filter,setFilter] = useState<Filter>('all');
  const [limit,setLimit] = useState(25);
  const calls = useMemo(() => selectDetails(stats?.detailCalls ?? [], filter), [stats?.detailCalls,filter]);
  const hours = useMemo(() => {
    const result = Array.from({length:24},(_,hour)=>({hour,count:0}));
    for (const c of calls) { const hour=DateTime.fromISO(c.offeredAt).setZone(ZONE).hour; if(Number.isInteger(hour) && result[hour]) result[hour].count++; }
    return result;
  },[calls]);
  const max = Math.max(1,...hours.map(h=>h.count));
  const answered=calls.filter(c=>c.outcome==='accepted').length, failed=calls.filter(c=>c.outcome==='missed').length;
  const missedNotify = (notify?.calls ?? []).filter(c => c.direction === 'in' && c.hangupType === 'missed' && DateTime.fromISO(c.firstReceivedAt).setZone(ZONE).toISODate()! >= from && DateTime.fromISO(c.firstReceivedAt).setZone(ZONE).toISODate()! <= to);
  const tabs: [Filter,string][] = [['all','Alle'],['incoming','Eingehend'],['outgoing','Ausgehend'],['failed','Nicht angenommen']];
  return <section className="panel"><div className="panel-heading"><div><h2>Anrufe und Zustellversuche im Detail</h2><p>Callcenter-Daten · nach Anrufbeginn · Zeitraum, Agent und Warteschleife wie oben ausgewählt</p></div><span className="count-tag">{verified && stats ? calls.length : '—'} Datensätze</span></div>
    <div className="mini-tabs" role="group" aria-label="Anrufart">{tabs.map(([id,label])=><button key={id} className={filter===id?'selected':''} aria-pressed={filter===id} onClick={()=>{setFilter(id);setLimit(25);}}>{label}</button>)}</div>
    <p className="chart-footnote">{verified && stats ? `${calls.length} Datensätze · ${answered} beantwortet · ${failed} nicht angenommen · ${calls.length-answered-failed} offen oder unbekannt` : 'Daten werden geladen.'} Ein Datensatz kann ein Zustellversuch sein.</p>
    <div className="chart" aria-label="Datensätze nach Tagesstunde"><svg viewBox="0 0 890 230" role="img" aria-label="Datensätze nach Tagesstunde">{hours.map(({hour,count})=>{const h=count/max*170;return <g key={hour}><rect x={35+hour*35} y={180-h} width="27" height={h} rx="3" fill="#547f6c"><title>{`${String(hour).padStart(2,'0')}:00 · ${count} Datensätze`}</title></rect>{hour%2===0&&<text x={48+hour*35} y="204" textAnchor="middle" className="chart-axis">{String(hour).padStart(2,'0')}</text>}</g>})}</svg></div>
    <div className="table-scroll"><table className="calls-table"><thead><tr><th>Richtung</th><th>Gegenstelle</th><th>Ergebnis</th><th>Agent</th><th>Warteschleife</th><th>Beginn</th><th>Ende</th><th>Dauer</th></tr></thead><tbody>{verified && calls.slice(0,limit).map(c=><tr key={c.id}><td>{direction(c)}</td><td className="phone-number">{c.callType==='dail_out' && !c.normalizedCaller && c.destination ? c.destination : <Caller number={c.normalizedCaller} names={names}/>}</td><td>{result(c)}</td><td>{agents.find(a=>a.id===c.agentId)?.name || c.agentName || 'Unzugeordnet'}</td><td>{queues.find(q=>q.id===c.queueId)?.name || c.queueName || 'Unbekannt'}</td><td>{timestamp(c.offeredAt)}</td><td>{timestamp(c.endedAt)}</td><td>{c.seconds!==null ? duration(c.seconds) : c.estimatedSeconds!==null ? `${duration(c.estimatedSeconds)} ≈` : '—'}</td></tr>)}</tbody></table></div>
    {(!verified || !stats || !calls.length) && <div className="empty"><CircleHelp size={27}/><p>{!stats?'Statistikdaten werden geladen …':'Keine passenden Datensätze im ausgewählten Zeitraum. Datenabdeckung beachten.'}</p></div>}
    {verified && calls.length>limit && <button className="text-button load-more" onClick={()=>setLimit(n=>n+50)}>Weitere 50 Datensätze anzeigen</button>}
    <div className="table-note"><CircleHelp size={15}/><span>„Nicht angenommen“ bezeichnet einen einzelnen Zustellversuch, keinen nachgewiesen insgesamt verpassten Anruf. Mehrere Versuche desselben Anrufers können erscheinen. {approximate?'≈ kennzeichnet eine Näherung einschließlich möglicher Klingel- oder Weiterleitungszeit. ':''}Ausgehende Zielnummern älterer Nextcloud-Importe können fehlen; unbekannte Zuordnungen bleiben sichtbar. Die Übersicht und Grafik oberhalb dieser Tabelle werten weiterhin beantwortete eingehende Anrufe aus.</span></div>
    {notify?.configured && <><div className="panel-heading"><div><h2>Verpasste Anrufe · Notify</h2><p>Von Placetel als insgesamt nicht angenommen gemeldete eingehende Anrufe</p></div></div>
      <p className="chart-footnote">Nur empfangene Ereignisse aus den letzten höchstens 50 Notify-Anrufen; Zeitraum nach Empfangszeit. Empfangsausfälle und ältere Ereignisse können fehlen. Agenten- und Warteschleifenfilter gelten hier nicht. Keine Zusammenrechnung mit obigen Callcenter-Datensätzen.</p>
      <div className="table-scroll"><table className="calls-table"><thead><tr><th>Anrufer</th><th>Angerufene Nummer</th><th>Erstes Ereignis empfangen</th><th>Ergebnis</th></tr></thead><tbody>{missedNotify.map(c=><tr key={c.id}><td><Caller number={c.caller} names={names}/></td><td>{c.to || 'Unbekannt'}</td><td>{timestamp(c.firstReceivedAt)}</td><td>Verpasst</td></tr>)}</tbody></table></div>
      {!missedNotify.length && <p className="chart-footnote">In den zuletzt empfangenen Ereignissen wurde für diesen Zeitraum kein verpasster Anruf gefunden; daraus folgt keine vollständige Erfassung.</p>}</>}
  </section>;
}
