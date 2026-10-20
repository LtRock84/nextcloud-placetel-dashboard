import { useEffect, useRef, useState } from 'react';
import { DateTime } from 'luxon';
import { AlertTriangle, ArrowDownLeft, Clock3, Headphones, PhoneIncoming, Radio } from 'lucide-react';
import { Caller } from './OperationsPanels';
import { ZONE, type Queue, type QueueSnapshot, type AlertSettings } from '../shared/types';

const stamp = (iso: string | null, format = 'HH:mm:ss') => iso ? DateTime.fromISO(iso).setZone(ZONE).toFormat(format) : '—';
function waitTime(seconds: number) {
  const total = Math.max(0, Math.floor(seconds));
  return total >= 3600 ? `${Math.floor(total / 3600)}:${String(Math.floor(total / 60) % 60).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
    : `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

export function QueueBoard({ queues, snapshot, demo, alerts = {warningSeconds:60,criticalSeconds:120,sound:false}, names = {} }: { queues: Queue[]; snapshot?: QueueSnapshot; demo: boolean; alerts?: AlertSettings; names?: Record<string,string> }) {
  const audio = useRef<AudioContext | null>(null);
  const sounded = useRef(new Set<string>());
  const lastTone = useRef(0);
  const [audioEnabled,setAudioEnabled] = useState(false);
  const [audioError,setAudioError] = useState('');
  const [clock, setClock] = useState(() => performance.now());
  const received = useRef({ serverNow: snapshot?.serverNow, at: performance.now() });
  if (received.current.serverNow !== snapshot?.serverNow) received.current = { serverNow: snapshot?.serverNow, at: performance.now() };
  useEffect(() => { const id = setInterval(() => setClock(performance.now()), 1000); return () => clearInterval(id); }, []);
  const now = snapshot ? Date.parse(snapshot.serverNow) + Math.max(0, clock - received.current.at) : 0;
  const fresh = !!snapshot?.fresh && !!snapshot.asOf && now - Date.parse(snapshot.asOf) < 30000;
  const counterAt = fresh ? now : snapshot?.asOf ? Date.parse(snapshot.asOf) : 0;
  const level = (enteredAt:string) => { const seconds=(counterAt-Date.parse(enteredAt))/1000; return !fresh?'stale':seconds>=alerts.criticalSeconds?'critical':seconds>=alerts.warningSeconds?'warning':'normal'; };
  useEffect(()=>{
    if(!fresh||!alerts.sound||!audioEnabled||!audio.current)return;
    const keys=snapshot?.queues.flatMap(q=>q.waitingCallers.filter(c=>['warning','critical'].includes(level(c.enteredAt))).map(c=>`${c.id}:${level(c.enteredAt)}`))??[];
    const newKeys=keys.filter(k=>!sounded.current.has(k));
    if(!newKeys.length||performance.now()-lastTone.current<30000)return;
    if(audio.current.state!=='running')return;
    try{const oscillator=audio.current.createOscillator(),gain=audio.current.createGain();oscillator.connect(gain);gain.connect(audio.current.destination);oscillator.frequency.value=660;gain.gain.setValueAtTime(0.08,audio.current.currentTime);gain.gain.exponentialRampToValueAtTime(0.001,audio.current.currentTime+0.35);oscillator.start();oscillator.stop(audio.current.currentTime+0.35);newKeys.forEach(k=>sounded.current.add(k));lastTone.current=performance.now();}catch{setAudioError('Hinweiston konnte nicht abgespielt werden.');}
  },[clock,snapshot,alerts.sound,audioEnabled,fresh]);
  useEffect(()=>()=>{void audio.current?.close();},[]);
  return <section className="panel queue-board"><div className="panel-heading"><div><h2>Anrufer je Warteschleife</h2><p>{demo ? 'Fiktive Beispieldaten' : 'Automatische API-Abfrage · Zielintervall 5 Sekunden'} · Stand {stamp(snapshot?.asOf ?? null)}</p></div><span className={`queue-feed-state ${fresh ? 'current' : ''}`}><Radio size={14} />{demo ? 'DEMO' : fresh ? 'Aktuell' : 'Stand verzögert'}</span></div>
    <div className="queue-alert-legend"><span><i className="alert-dot warning"/>Gelb ab {alerts.warningSeconds} s</span><span><i className="alert-dot critical"/>Rot ab {alerts.criticalSeconds} s</span>{alerts.sound&&<button className="text-button" onClick={()=>{try{audio.current??=new AudioContext();void audio.current.resume().then(()=>{setAudioEnabled(true);lastTone.current=performance.now()-30000;setAudioError('');}).catch(()=>setAudioError('Audiofreigabe fehlgeschlagen.'));}catch{setAudioError('Dieser Browser unterstützt die Audiofreigabe nicht.');}}}>{audioEnabled?'Hinweiston freigegeben':'Hinweiston auf diesem Gerät freigeben'}</button>}{audioError&&<span role="alert">{audioError}</span>}</div>
    {!fresh && <div className="message warning"><AlertTriangle size={16} /><span>{snapshot?.asOf ? 'Letzter bekannter Stand. Die Anruferliste ist möglicherweise veraltet; Wartezeiten sind bis zum nächsten erfolgreichen Abruf eingefroren.' : 'Die erste vollständige Anruferliste wird geladen.'}</span></div>}
    {!!snapshot?.unmappedWaiting && <div className="message warning"><AlertTriangle size={16} /><span>{snapshot.unmappedWaiting} wartende Anrufe können keiner Warteschleife eindeutig zugeordnet werden.</span></div>}
    <div className="queue-live-grid">{queues.map((queue, index) => {
      const entry = snapshot?.queues.find(q => q.id === queue.id);
      const queueLevel=entry?.waitingCallers.some(c=>level(c.enteredAt)==='critical')?'critical':entry?.waitingCallers.some(c=>level(c.enteredAt)==='warning')?'warning':'normal';
      return <article className={`queue-live-card queue-alert-${queueLevel}`} key={queue.id}><div className="queue-live-heading"><div className={`queue-icon queue-tone-${index % 3}`}><Headphones size={20} /></div><div><h3>{queue.name}</h3><p>{queue.description || 'Callcenter-Warteschleife'}</p></div><span className={`waiting-badge ${entry?.waiting ? 'has-waiting' : ''}`}>{entry && snapshot?.asOf ? entry.waiting : '—'} wartend</span></div>
        <div className="queue-list-heading"><span><PhoneIncoming size={14} />Wartende Anrufer</span><small>Älteste Anrufe zuerst</small></div>
        <div className="waiting-caller-list">{entry?.waitingCallers.map((call, i) => <div className={`queue-caller waiting-caller alert-${level(call.enteredAt)}`} key={call.id}><span className="caller-position">{i + 1}</span><div className="queue-caller-main"><Caller number={call.caller} names={names} /><small>{call.phase === 'assigned' ? `In Zustellung${call.agentName ? ` · ${call.agentName}` : ''}` : 'Wartet auf freien Agenten'} · seit {stamp(call.enteredAt)}</small></div><span className="waiting-duration"><Clock3 size={12} />{waitTime((counterAt - Date.parse(call.enteredAt)) / 1000)}<small>{level(call.enteredAt)==='critical'?'Lange Wartezeit':level(call.enteredAt)==='warning'?'Warnschwelle erreicht':'seit Anrufbeginn'}</small></span></div>)}</div>
        {!entry?.waitingCallers.length && <p className="queue-list-empty">{!snapshot?.asOf ? 'Anruferliste wird geladen …' : fresh ? 'Aktuell keine wartenden Anrufer.' : 'Im letzten bekannten Stand keine wartenden Anrufer.'}</p>}
        <div className="queue-list-heading recent-heading"><span><ArrowDownLeft size={14} />Zuletzt angenommen</span><small>Die letzten 3 Anrufe</small></div>
        <div className="recent-caller-list">{entry?.recentAccepted.map(call => <div className="queue-caller recent-caller" key={call.id}><span className="recent-call-icon"><ArrowDownLeft size={15} /></span><div className="queue-caller-main"><Caller number={call.caller} names={names} /><small>{call.agentName || 'Agent nicht eindeutig zugeordnet'} · {call.endedAt ? 'Beendet' : 'Ende noch offen'}</small></div><div className="recent-call-time"><strong>{stamp(call.at, 'HH:mm')}</strong><small>{stamp(call.at, 'dd.MM.')}</small></div><small className="recent-time-basis">{call.timeBasis === 'accepted' ? 'Annahmezeit' : call.timeBasis === 'queue-exit' ? 'Zeitpunkt Warteschleifenaustritt' : 'Anrufbeginn; Annahmezeit nicht verfügbar'}</small></div>)}</div>
        {!entry?.recentAccepted.length && <p className="queue-list-empty">Noch keine angenommenen Anrufe in der geladenen Historie.</p>}
      </article>;
    })}</div>
    {!queues.length && <p className="queue-list-empty">Warteschleifen werden geladen …</p>}
    <div className="chart-footnote">Die Zeiten in der Warteliste zählen ab dem gemeldeten Anrufbeginn. Die Listen werden nach vollständigem Abruf aller Datenseiten erneuert. API-Limits und die Aktualisierung bei Placetel können die Anzeige verzögern. „In Zustellung“ bleibt bis zur gemeldeten Annahme in der Warteliste.</div>
  </section>;
}
