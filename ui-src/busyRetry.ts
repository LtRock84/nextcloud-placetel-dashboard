/** Only retry a server response proving that the dashboard lock was not acquired. */
export async function requestWithBusyRetry<T>(attempt:()=>Promise<{ok:boolean;status:number;value:T&{error?:string}}>,options:{onWait:(waiting:boolean)=>void;delay?:()=>Promise<void>;maxAttempts?:number}):Promise<{ok:boolean;status:number;value:T&{error?:string}}> {
 const wait=options.delay??(()=>new Promise<void>(resolve=>setTimeout(resolve,800)));
 const limit=options.maxAttempts??30;let waiting=false;
 try{for(let i=0;i<limit;i++){const response=await attempt();if(response.status!==409||response.value?.error!=='Ein Abruf läuft. Bitte gleich erneut versuchen.')return response;if(!waiting){waiting=true;options.onWait(true);}if(i<limit-1)await wait();else return response;}throw new Error('Wartezeit überschritten.');}
 finally{if(waiting)options.onWait(false);}
}
