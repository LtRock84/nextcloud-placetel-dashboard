import {useEffect, useId, useRef, useState} from 'react';
import {Heart, X} from 'lucide-react';

// Same public recipient as Werkstattmanager; never supplied by settings or caller data.
const PROJECT_SUPPORT_URL = 'https://www.paypal.me/Werkstattmanager';

function SupportDialog({close}: {close: () => void}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  useEffect(() => {
    const element = dialog.current;
    if (element && !element.open) element.showModal();
    return () => {if (element?.open) element.close();};
  }, []);
  return <dialog ref={dialog} className="project-support-dialog" aria-labelledby={titleId}
    onCancel={event => {event.preventDefault(); close();}}
    onClick={event => {if (event.target === dialog.current) close();}}>
    <div className="modal-head"><h2 id={titleId}>Placetel Dashboard unterstützen</h2><button type="button" className="icon-button" aria-label="Hinweis schließen" onClick={close}><X size={20}/></button></div>
    <div className="settings-body">
      <p>Dein Beitrag hilft, die Projektkosten und die Weiterentwicklung des Placetel Dashboards zu finanzieren.</p>
      <p>Die Unterstützung ist freiwillig. Die App bleibt kostenlos nutzbar. Eine Zahlung schaltet keine zusätzlichen Funktionen frei und begründet keinen Anspruch auf individuelle Leistungen.</p>
      <p>Es wird keine Spendenbescheinigung ausgestellt. Die Unterstützung ist keine steuerbegünstigte Spende.</p>
      <p>Erst mit „Zu PayPal“ verlässt du die App. Es werden keine Anruf-, Kontakt- oder Zugangsdaten übermittelt und keine Zahlungsdaten in der App gespeichert. Prüfe Empfänger und Betrag auf PayPal, bevor du zahlst.</p>
      <p>Der Link führt zum selben PayPal-Profil wie im Werkstattmanager: <strong>Werkstattmanager</strong>. Den Betrag wählst du dort selbst; die App richtet keine wiederkehrenden Zahlungen ein.</p>
    </div>
    <div className="modal-actions"><button type="button" className="button" onClick={close}>Zurück zum Dashboard</button><a className="button primary" href={PROJECT_SUPPORT_URL} target="_blank" rel="noopener noreferrer" referrerPolicy="no-referrer">Zu PayPal (neuer Tab)</a></div>
  </dialog>;
}

export function ProjectSupport() {
  const [open, setOpen] = useState(false);
  return <section className="project-support" aria-label="Projekt unterstützen">
    <button type="button" className="project-support-button" title="Projekt unterstützen" aria-label="Projekt unterstützen" onClick={() => setOpen(true)}><Heart size={18} aria-hidden="true"/><span>Projekt unterstützen</span></button>
    <small>Freiwilliger Beitrag zur Weiterentwicklung</small>
    {open && <SupportDialog close={() => setOpen(false)}/>}
  </section>;
}
