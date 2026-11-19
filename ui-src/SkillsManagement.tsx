import {useState} from 'react';
import type {Agent, Queue} from '../shared/types';

type Skill={id:string;name:string;queueIds:string[]};
type Snapshot={agentId:string;skills:Skill[];current:Record<string,number>};
type Change=Skill&{before:number|null;after:number|null};
type Preview={token:string;changes:Change[]};
type Request=<T>(path:string,data?:unknown,method?:string)=>Promise<T>;

export function SkillsManagement({agents,queues,request,write}:{agents:Agent[];queues:Queue[];request:Request;write:boolean}){
 const [agent,setAgent]=useState('');const [snapshot,setSnapshot]=useState<Snapshot|null>(null);const [selected,setSelected]=useState<Record<string,number>>({});
 const [preview,setPreview]=useState<Preview|null>(null);const [busy,setBusy]=useState(false);const [error,setError]=useState('');const [notice,setNotice]=useState('');
 const execute=async(fn:()=>Promise<void>)=>{setBusy(true);setError('');setNotice('');try{await fn();}catch(e){setError(e instanceof Error?e.message:'Vorgang fehlgeschlagen.');}finally{setBusy(false);}};
 const load=async()=>{setPreview(null);setSnapshot(null);const value=await request<Snapshot>(`skills?agentId=${encodeURIComponent(agent)}`);setSnapshot(value);setSelected({...value.current});};
 const names=(ids:string[])=>ids.map(id=>queues.find(q=>q.id===id)?.name??`Warteschleife ${id}`).join(', ')||'Keine Warteschleife';
 return <section className="panel"><div className="panel-heading"><div><h2>Fähigkeiten und Warteschleifen</h2><p>Ein Skill kann mehrere Warteschleifen zuordnen.</p></div></div><div className="settings-body">
 <p>Wähle einen Agenten und lade seine aktuellen Skills. Höhere Stufen werden laut Placetel zuerst berücksichtigt. Änderungen wirken auf alle beim Skill aufgeführten Warteschleifen.</p>
 <div className="inline-actions"><label className="field">Agent<select value={agent} disabled={busy} onChange={e=>{setAgent(e.target.value);setSnapshot(null);setPreview(null);setError('');}}><option value="">Agent wählen</option>{agents.map(a=><option key={a.id} value={a.id}>{a.name}</option>)}</select></label><button className="button" disabled={!agent||busy} onClick={()=>void execute(load)}>{busy?'Lädt …':'Skills laden'}</button></div>
 {error&&<div className="message error" role="alert">{error}</div>}{notice&&<div className="message" role="status">{notice}</div>}
 {snapshot&&<><div className="table-scroll"><table><thead><tr><th>Skill</th><th>Warteschleifen</th><th>Aktiv</th><th>Stufe</th></tr></thead><tbody>{snapshot.skills.map(s=><tr key={s.id}><td>{s.name}</td><td>{names(s.queueIds)}</td><td><input type="checkbox" aria-label={`${s.name} zuordnen`} disabled={!write||busy} checked={Object.hasOwn(selected,s.id)} onChange={e=>{setPreview(null);setSelected(old=>{const next={...old};if(e.target.checked)next[s.id]=1;else delete next[s.id];return next;});}}/></td><td>{Object.hasOwn(selected,s.id)&&<input type="number" aria-label={`Stufe ${s.name}`} min={-2147483648} max={2147483647} step={1} disabled={!write||busy} value={selected[s.id]} onChange={e=>{setPreview(null);const value=e.target.valueAsNumber;setSelected(old=>({...old,[s.id]:value}));}}/>}</td></tr>)}</tbody></table></div>{!snapshot.skills.length&&<p>Keine Skills vorhanden.</p>}
 <button className="button primary" disabled={!write||busy||!snapshot.skills.length||Object.values(selected).some(n=>!Number.isInteger(n)||n< -2147483648||n>2147483647)} onClick={()=>void execute(async()=>{const skills=Object.entries(selected).map(([skillId,level])=>({skillId,level}));setPreview(await request<Preview>('skills/preview',{agentId:agent,skills}));})}>Änderungen prüfen</button></>}
 {preview&&<div className="change-preview"><h3>Änderungen bestätigen</h3><p>Diese Änderungen betreffen die aufgeführten Warteschleifen in der Telefonanlage.</p>{preview.changes.map(c=><div key={c.id}><strong>{c.name}</strong><p>{names(c.queueIds)}</p><p>{c.before===null?'Nicht zugeordnet':`Stufe ${c.before}`} → {c.after===null?'Nicht zugeordnet':`Stufe ${c.after}`}</p></div>)}<div className="inline-actions"><button className="button" disabled={busy} onClick={()=>setPreview(null)}>Abbrechen</button><button className="button primary" disabled={!write||busy} onClick={()=>void execute(async()=>{const token=preview.token;setPreview(null);try{await request('skills/apply',{token});setSnapshot(null);setNotice('Änderungen bestätigt. Bitte Skills erneut laden.');}catch(e){setSnapshot(null);throw e;}})}>Änderungen jetzt übernehmen</button></div></div>}
 </div></section>;
}
