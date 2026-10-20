export type CloudConfig = { api: string; native: boolean; role: 'viewer'|'operator'|'admin'; help: string; token: string };
export function cloudConfig(): CloudConfig | null {
  if(typeof document === 'undefined') return null;
  const root=document.getElementById('placetel-root');
  if(!root) return null;
  return {native:root.dataset.native==='1',api:root.dataset.api!.replace(/\/bootstrap$/,''),role:root.dataset.role as CloudConfig['role'],help:root.dataset.help!,token:(window as Window & {OC?:{requestToken?:string}}).OC?.requestToken || ''};
}
export function helpUrl(name: string) { const cloud=cloudConfig();return cloud ? cloud.help.replace(/ereignisanbindung$/,name):`/${name}.txt`; }

export function updateCloudIdentity(role: unknown) {if(typeof document!=='undefined'&&typeof role==='string'&&['viewer','operator','admin'].includes(role)){const root=document.getElementById('placetel-root');if(root)root.dataset.role=role;}}

export function notifyAddress(api:string,base:string):string|null {
 try {const url=new URL(api,base);if(!url.pathname.endsWith('/api'))return null;url.pathname=url.pathname.replace(/\/api$/,'/notify');url.search='';url.hash='';return url.href;}catch{return null;}
}
