import type {CallbackTask} from '../shared/types';
/** Possible matches are a display category, never an automatic state change. */
export function matchesCallbackStatus(task:Pick<CallbackTask,'state'|'possibleAcceptedAt'>,filter:string):boolean {
 if(!filter)return true;
 if(filter==='open')return task.state==='open'&&!task.possibleAcceptedAt;
 if(filter==='possible')return task.state==='open'&&!!task.possibleAcceptedAt;
 return task.state===filter;
}
