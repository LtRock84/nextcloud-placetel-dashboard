import {useState} from 'react';
import rawGuide from './setup-guide.json';
import {cloudConfig,helpUrl,notifyAddress} from './hosting';

type GuideSection={id:string;title:string;audience:string;steps:{title:string;text:string[];code?:string}[]};
type Guide={title:string;intro:string[];exampleNotifyUrl:string;sections:GuideSection[];sources:{title:string;url:string}[]};
const guide:Guide=rawGuide;
const guideId='placetel-setup-guide';
function guideScope():Document|ShadowRoot {return document.getElementById('placetel-root')?.shadowRoot??document;}
export function SetupGuideLink({chapter,label}:{chapter?:string;label?:string}) {
 return <button type="button" className="button" onClick={()=>{const scope=guideScope();const root=scope.getElementById(guideId) as HTMLDetailsElement|null;if(!root)return;root.open=true;const target=chapter?scope.getElementById(`${guideId}-${chapter}`) as HTMLDetailsElement|null:null;if(target)target.open=true;(target??root).scrollIntoView({behavior:'smooth',block:'start'});}}>{label??'Schritt-für-Schritt-Anleitung anzeigen'}</button>;
}
function GuideCode({code}:{code:string}) {
 const [message,setMessage]=useState('');
 return <div className="guide-code"><pre tabIndex={0}><code>{code}</code></pre><button type="button" className="button" onClick={()=>{void(async()=>{try{await navigator.clipboard.writeText(code);setMessage('Vorlage kopiert. Eigene Werte vor dem Ausführen prüfen und ersetzen.');}catch{setMessage('Bitte den Text im Codekasten markieren und manuell kopieren.');}})();}}>Befehl / Vorlage kopieren</button><span role="status" className="field-help">{message}</span></div>;
}
export function SetupGuide() {
 const cloud=cloudConfig();
 if(!cloud?.native)return null;
 const derived=typeof window!=='undefined'?notifyAddress(cloud.api,window.location.href):null;
 const address=derived?.startsWith('https://')?derived:guide.exampleNotifyUrl;
 const path=new URL(address).pathname;
 const expand=(text:string)=>text.replaceAll('{{NOTIFY_URL}}',address).replaceAll('{{NOTIFY_PATH}}',path);
 return <details id={guideId} className="setup-guide"><summary>Ausführliche Einrichtungsanleitung anzeigen <span>Nextcloud-Administration · HTTPS · Placetel · DispoLive · PHP-Worker</span></summary><div className="setup-guide-body">
  <h3>{guide.title}</h3>{guide.intro.map(text=><p key={text}>{text}</p>)}
  <p className="message">Befehle werden nur angezeigt oder kopiert. Die App verändert damit keine Firewall, Proxykonfiguration oder Serverdienste.</p>
  {derived?.startsWith('https://')?<p>Adressbeispiele verwenden die aktuell geöffnete Nextcloud: <code>{address}</code>. Das ist noch kein externer Verbindungstest.</p>:<p className="message warning">Die aktuelle Ansicht hat keine HTTPS-Adresse. Diese Anleitung verwendet deshalb die Beispieladresse {guide.exampleNotifyUrl}. Erst über die eigene öffentliche HTTPS-Adresse öffnen oder die Beispiele vor Verwendung anpassen.</p>}
  <nav className="guide-chapters" aria-label="Kapitel der Einrichtungsanleitung">{guide.sections.map((s,i)=><SetupGuideLink key={s.id} chapter={s.id} label={`${i+1}. ${s.title}`}/>)}</nav>
  <div className="guide-actions"><button type="button" className="button" onClick={()=>{guideScope().getElementById(guideId)?.querySelectorAll<HTMLDetailsElement>('details').forEach(d=>{d.open=true;});}}>Alle Kapitel aufklappen</button><a className="external-link" href={helpUrl('ereignisanbindung')} target="_blank" rel="noreferrer">Vollständige Textanleitung öffnen / drucken ↗</a></div>
  {guide.sections.map((section,index)=><details key={section.id} id={`${guideId}-${section.id}`} className="guide-section"><summary>{index+1}. {section.title}<span>{section.audience}</span></summary><ol>{section.steps.map(step=><li key={step.title}><h4>{step.title}</h4>{step.text.map(text=><p key={text}>{expand(text)}</p>)}{step.code&&<GuideCode code={expand(step.code).trimEnd()}/>}</li>)}</ol></details>)}
  <h4>Offizielle Dokumentationen</h4><ul className="guide-sources">{guide.sources.map(source=><li key={source.url}><a href={source.url} target="_blank" rel="noreferrer">{source.title} ↗</a></li>)}</ul>
 </div></details>;
}
