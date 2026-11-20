import {useEffect,useState} from 'react';
import type {Agent,Queue} from '../shared/types';
type Skill={id:string;name:string;queueIds:string[]};
type Snapshot={skills:Skill[];current:Record<string,number>};
type Entry={snapshot?:Snapshot;fetchedAt?:string;error?:boolean};
type Request=<T>(path:string,data?:unknown,method?:string)=>Promise<T>;
export function useAgentSkillSummaries(agents:Agent[],enabled:boolean,request:Request){
 const [values,setValues]=useState<Record<string,Entry>>({});const ids=agents.map(a=>a.id).join(',');
 useEffect(()=>{if(!enabled){setValues({});return;}let active=true;let timer:ReturnType<typeof setTimeout>;const load=async()=>{for(const id of ids.split(',').filter(Boolean)){if(!active)return;try{const snapshot=await request<Snapshot>(`skills?agentId=${encodeURIComponent(id)}`);if(active)setValues(old=>({...old,[id]:{snapshot,fetchedAt:new Date().toISOString()}}));}catch{if(active)setValues(old=>({...old,[id]:{...old[id],error:true}}));}}if(active)timer=setTimeout(()=>void load(),60000);};void load();return()=>{active=false;clearTimeout(timer);};},[enabled,ids,request]);
 return values;
}
export function AgentSkillSummary({entry}:{entry?:Entry;queues:Queue[]}){
 if(!entry?.snapshot)return <small className="agent-skill-summary">{entry?.error?'Skills konnten nicht geladen werden':'Skills werden geladen …'}</small>;
 const selected=entry.snapshot.skills.filter(s=>Object.hasOwn(entry.snapshot!.current,s.id));
 return <small className="agent-skill-summary"><span>Skills: {selected.map(s=>`${s.name} (${entry.snapshot!.current[s.id]})`).join(', ')||'Keine'}</span><span className="agent-skill-updated">{entry.error?'Letzter Abruf fehlgeschlagen · ':''}Stand {entry.fetchedAt?new Date(entry.fetchedAt).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit',second:'2-digit'}):'unbekannt'}</span></small>;
}
