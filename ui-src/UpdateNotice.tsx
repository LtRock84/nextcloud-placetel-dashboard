import { useEffect, useRef, useState } from 'react';
import { Sparkles } from 'lucide-react';

type Note = { title:string; description:string };
export type UpdateInfo = { version:string; pending:boolean; releases:{version:string;items:Note[]}[] };
type Request = <T>(path:string,data?:unknown)=>Promise<T>;

export function UpdateNotice({request}:{request:Request}) {
 const [info,setInfo]=useState<UpdateInfo|null>(null),[busy,setBusy]=useState(false),[error,setError]=useState('');
 const dialog=useRef<HTMLDialogElement>(null);
 useEffect(()=>{let active=true;void request<UpdateInfo>('updates').then(value=>{if(active)setInfo(value);}).catch(()=>{if(active)setError('Die Update-Hinweise konnten nicht geladen werden. Bitte die Seite später erneut öffnen.');});return ()=>{active=false;};},[request]);
 useEffect(()=>{if(info?.pending&&dialog.current&&!dialog.current.open)dialog.current.showModal();},[info]);
 const acknowledge=async()=>{if(!info||busy)return;setBusy(true);setError('');try{await request('updates/seen',{version:info.version});setInfo({...info,pending:false});}catch{setError('Die Bestätigung konnte nicht gespeichert werden. Bitte erneut versuchen.');}finally{setBusy(false);}};
 if(!info?.pending)return error?<p className="message warning" role="status">{error}</p>:null;
 return <dialog ref={dialog} className="update-notice" aria-labelledby="update-notice-title" onCancel={e=>{e.preventDefault();void acknowledge();}}>
  <div className="update-notice-heading"><span className="update-notice-icon"><Sparkles size={22}/></span><div><span className="update-notice-kicker">PLACETEL DASHBOARD · UPDATE</span><h2 id="update-notice-title">Das ist neu</h2><p>Version {info.version} ist installiert. Hier findest du die wichtigsten Änderungen.</p></div></div>
  <div className="update-notice-releases">{info.releases.map(release=><section key={release.version}><h3>Version {release.version}</h3><ul>{release.items.map((item,i)=><li key={i}><strong>{item.title}</strong><p>{item.description}</p></li>)}</ul></section>)}</div>
  {error&&<p className="message error" role="alert">{error}</p>}
  <div className="modal-actions"><button className="button primary" onClick={()=>void acknowledge()} disabled={busy}>{busy?'Wird gespeichert …':'Verstanden'}</button></div>
 </dialog>;
}
