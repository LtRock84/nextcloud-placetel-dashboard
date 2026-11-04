import { useEffect, useState } from 'react';
import { DateTime } from 'luxon';
import { ZONE, type CallbackState } from '../shared/types';

type Source='placetel-calls'|'notify';
type TestCall={source:Source;id:string;caller:string|null;destination:string|null;at:string;timeBasis:'provider'|'received';outcome:'accepted'|'missed'|'voicemail'|'blocked'|'unknown';state:CallbackState;note:string};
type Report={day:string;calls:TestCall[];notify:TestCall[];attempts:{agent:string;outcome:string}[];invalid:number;fetchedAt:string};
type Request=<T>(path:string,data?:unknown,method?:string)=>Promise<T>;
const labels:Record<TestCall['outcome'],string>={accepted:'Angenommen',missed:'Verpasst',voicemail:'Mailbox',blocked:'Blockiert',unknown:'Unbekannt'};
const statuses:Record<CallbackState,string>={open:'Offen',later:'Später',done:'Erledigt'};

export function IncomingTestModel({request}:{request:Request}) {
 const [day,setDay]=useState(()=>DateTime.now().setZone(ZONE).toISODate()!);
 const [source,setSource]=useState<Source>('placetel-calls');
 const [filter,setFilter]=useState('missed');
 const [report,setReport]=useState<Report|null>(null),[error,setError]=useState('');
 const [edit,setEdit]=useState<TestCall|null>(null),[busy,setBusy]=useState(false);
 const load=()=>request<Report>(`test-model/incoming?${new URLSearchParams({day})}`);
 useEffect(()=>{let active=true;setReport(null);void load().then(x=>{if(active){setReport(x);setError('');}}).catch(e=>{if(active)setError(e.message);});return()=>{active=false;};},[day]);
 const rows=(source==='placetel-calls'?report?.calls:report?.notify)?.filter(c=>filter==='all'||c.outcome===filter)??[];
 const save=()=>{if(!edit)return;setBusy(true);setError('');void request('test-model/incoming/state',{day,source:edit.source,id:edit.id,state:edit.state,note:edit.note}).then(load).then(x=>{setReport(x);setEdit(null);}).catch(e=>setError(e.message)).finally(()=>setBusy(false));};
 return <section className="panel incoming-test-panel" aria-label="Anrufbezogene Testauswertung">
  <div className="panel-heading"><div><h2>Anrufbezogene Rückrufliste · Testmodell</h2><p>Fiktive Anrufe für die Prüfung der neuen Auswertung</p></div><span className="count-tag">Nur Testdaten</span></div>
  <div className="settings-body">
   <p className="message warning">Die Quellen werden getrennt angezeigt. Das Beispiel enthält einen nach mehreren Zustellversuchen angenommenen Anruf um 09:00 Uhr und einen späteren, eigenständigen verpassten Anruf derselben Nummer um 10:00 Uhr. Eine Warteschleife ist hier nicht belegt. Diese Testansicht ist noch nicht mit der echten Anlage verbunden.</p>
   {report&&<p className="chart-footnote">Testfall um 09:00: {report.attempts.map(a=>`${a.agent}: ${a.outcome==='accepted'?'angenommen':'nicht angenommen'}`).join(' → ')}. Im simulierten Anrufprotokoll steht dafür ein angenommener Anruf.</p>}
   <div className="settings-tabbar" role="tablist" aria-label="Testdatenquelle"><button role="tab" aria-selected={source==='placetel-calls'} className={source==='placetel-calls'?'selected':''} onClick={()=>setSource('placetel-calls')}>Placetel-Anrufliste</button><button role="tab" aria-selected={source==='notify'} className={source==='notify'?'selected':''} onClick={()=>setSource('notify')}>Notify-Ereignisse</button></div>
   <div className="ops-filters"><label>Testtag<input aria-label="Testtag" type="date" value={day} max={DateTime.now().setZone(ZONE).toISODate()!} onChange={e=>{if(e.target.value){setEdit(null);setDay(e.target.value);}}}/></label><label>Ergebnis<select aria-label="Anrufergebnis" value={filter} onChange={e=>setFilter(e.target.value)}><option value="missed">Verpasst</option><option value="accepted">Angenommen</option><option value="voicemail">Mailbox</option><option value="all">Alle Ergebnisse</option></select></label></div>
   <p className="chart-footnote">{source==='placetel-calls'?'Placetel-Zeitpunkt; mehrseitiger Abruf simuliert.':'Empfangszeit des Ereignisses, keine bestätigte Anlagenzeit.'} Testdaten: {report?.invalid??0} ungültige Datensätze. Die tatsächliche Abdeckung ist noch nicht geprüft.</p>
   {error&&<p className="danger" role="alert">{error}</p>}
   <div className="table-scroll"><table><thead><tr><th>Anrufer</th><th>Angerufene Nummer</th><th>Zeitpunkt</th><th>Ergebnis</th><th>Rückrufstatus</th></tr></thead><tbody>{rows.map(row=><tr key={`${row.source}:${row.id}`}><td>{row.caller??'Unterdrückt / unbekannt'}</td><td>{row.destination??'Unbekannt'}</td><td>{DateTime.fromISO(row.at).setZone(ZONE).toFormat('dd.MM.yyyy HH:mm')}</td><td>{labels[row.outcome]}</td><td>{row.outcome==='missed'?<button className="button" onClick={()=>setEdit(row)}>{statuses[row.state]} · Bearbeiten</button>:'Kein Rückrufkandidat'}{row.note&&<small className="block muted">{row.note}</small>}</td></tr>)}</tbody></table></div>
   {!rows.length&&<p className="muted">{report?'Keine passenden Testanrufe.':'Testdaten werden geladen …'}</p>}
   {edit&&<div className="message" role="region" aria-label="Test-Rückruf bearbeiten"><strong>Test-Rückruf bearbeiten</strong><p>{edit.caller??'Rufnummer unterdrückt'}</p><label className="field">Status<select aria-label="Test-Rückrufstatus" value={edit.state} onChange={e=>setEdit({...edit,state:e.target.value as CallbackState})}><option value="open">Offen</option><option value="later">Später</option><option value="done">Erledigt</option></select></label><label className="field">Notiz<textarea aria-label="Testnotiz" value={edit.note} maxLength={1000} onChange={e=>setEdit({...edit,note:e.target.value})}/></label><div className="inline-actions"><button className="button primary" disabled={busy} onClick={save}>Teststatus speichern</button><button className="button" disabled={busy} onClick={()=>setEdit(null)}>Abbrechen</button></div></div>}
  </div>
 </section>;
}
