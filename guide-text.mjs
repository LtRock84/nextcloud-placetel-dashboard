// Same content source as the in-app guide; examples must never contain customer data.
export function guideToText(guide) {
 const url=guide.exampleNotifyUrl,path=new URL(url).pathname;
 const expand=text=>text.replaceAll('{{NOTIFY_URL}}',url).replaceAll('{{NOTIFY_PATH}}',path);
 const lines=[guide.title,'',...guide.intro,'','Beispieladresse: '+url,'Alle Beispielwerte vor Verwendung an die eigene Installation anpassen.',''];
 for(const [chapter,section] of guide.sections.entries()){
  lines.push(`${chapter+1}. ${section.title}`,`Zuständig: ${section.audience}`,'');
  for(const [index,step] of section.steps.entries()){
   lines.push(`${chapter+1}.${index+1} ${step.title}`,...step.text.flatMap(text=>[expand(text),'']));
   if(step.code)lines.push('Befehl / Vorlage (wird nicht automatisch ausgeführt):',expand(step.code).trimEnd(),'');
  }
 }
 lines.push('Offizielle Dokumentationen',...guide.sources.map(s=>s.title+': '+s.url),'');return lines.join('\n');
}
