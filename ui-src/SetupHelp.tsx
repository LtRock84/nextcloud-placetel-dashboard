import {useState} from 'react';
import {cloudConfig,notifyAddress} from './hosting';
import {SetupGuideLink} from './SetupGuide';
export {notifyAddress} from './hosting';

export function PlacetelKeyHelp({id}:{id:string}) {
 return <small id={id} className="field-help">Melde dich als Administrator im <a href="https://web.placetel.de/integrations/web_api" target="_blank" rel="noreferrer">Placetel-Portal → Web-API</a> an. Aktiviere die Integration bei Bedarf und erstelle dort einen API-Schlüssel für diese App. Füge nur den Schlüssel ein, ohne „Bearer“ davor. Dein normales Placetel-Passwort gehört nicht in dieses Feld. Voraussetzung: Placetel PROFI mit API-/Callcenter-Berechtigung.</small>;
}

export function NotifySetupHelp() {
 const [notice,setNotice]=useState('');
 const cloud=cloudConfig();
 const address=cloud&&typeof window!=='undefined'?notifyAddress(cloud.api,window.location.href):null;
 if(!cloud?.native)return null;
 return <div className="setup-instructions"><p><strong>Optional: Anrufereignisse empfangen</strong></p><p>Für Dashboard, Telefonbuch und Statistiken ist dieser Schritt nicht erforderlich. DispoLive-Popups lassen sich anschließend separat unter „Zusatzfunktion: DispoLive“ einschalten.</p>
  {address&&<><label className="field">Empfangsadresse für Placetel<input readOnly value={address} aria-describedby="notify-address-help" onFocus={e=>e.currentTarget.select()}/><small id="notify-address-help" className="field-help">Aus der geöffneten Nextcloud-Adresse ermittelt, einschließlich eines möglichen Unterverzeichnisses. Im Placetel-Portal unter „Einstellungen → Externe APIs“ als Ereignisadresse eintragen. Die Adresse muss von Placetel über öffentliches HTTPS erreichbar sein. Bei interner Adresse oder vorgeschalteter Zugangssperre hilft dein Nextcloud-Administrator.</small></label><button type="button" className="button" onClick={()=>{void (async()=>{try{await navigator.clipboard.writeText(address);setNotice('Empfangsadresse kopiert.');}catch{setNotice('Bitte die Empfangsadresse im Feld markieren und manuell kopieren.');}})();}}>Empfangsadresse kopieren</button></>}
  {address&&!address.startsWith('https://')&&<p className="message warning">Diese Ansicht verwendet HTTP. Für Placetel benötigst du die öffentliche HTTPS-Adresse deiner Nextcloud. Öffne die App darüber erneut, bevor du die Empfangsadresse übernimmst.</p>}
  <ol><li>Dasselbe gemeinsame Geheimnis hier und im Placetel-Portal hinterlegen.</li><li>Notify für die gewünschten Rufnummern aktivieren. Dafür muss das Anrufrouting nicht auf „External API“ umgestellt werden.</li><li>Einen kontrollierten Testanruf durchführen. Unter „Ereignisse“ müssen Empfang und Anruf sichtbar werden. Ein gespeichertes Geheimnis allein bestätigt noch keine funktionierende Zustellung.</li></ol>
  <SetupGuideLink chapter="https" label="HTTPS-Empfang sicher einrichten: Schritt für Schritt"/><p><a href="https://github.com/Placetel/call-control-notify-api#setup" target="_blank" rel="noreferrer">Offizielle Placetel-Einrichtung öffnen ↗</a></p><p role="status">{notice}</p>
 </div>;
}

export function NotifySecretTools({disabled,onGenerate}:{disabled:boolean;onGenerate:(value:string)=>void}) {
 const [notice,setNotice]=useState('');
 return <><button type="button" className="button" disabled={disabled} onClick={()=>{try{const value=Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');onGenerate(value);setNotice('Zufälliges Geheimnis erzeugt. Vor dem Speichern für das Placetel-Portal kopieren.');}catch{setNotice('Bitte mit deinem Passwortmanager ein zufälliges Geheimnis erzeugen.');}}}>Zufälliges Geheimnis erzeugen</button><small className="field-help" role="status">{notice}</small></>;
}
