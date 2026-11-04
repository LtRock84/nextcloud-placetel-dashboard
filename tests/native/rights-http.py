import os,urllib.request,urllib.error,urllib.parse,http.cookiejar,json,re,html,pathlib,subprocess,time
if os.environ.get('PLACETEL_DISPOSABLE_TEST')!='1':raise SystemExit('Nur in expliziter Wegwerf-Testinstanz ausführen.')
root=pathlib.Path(os.environ.get('PLACETEL_NATIVE_FIXTURE_DIR','/tmp/placetel-native-20261020'));base='http://127.0.0.1:'+os.environ.get('PLACETEL_NATIVE_PORT','44326');container=os.environ.get('PLACETEL_NATIVE_NAME','placetel-native')+'-nc';checks=[]
def check(label,ok):
 checks.append({'test':label,'passed':bool(ok)})
 print(('PASS ' if ok else 'FAIL ')+label,flush=True)
 if not ok:raise RuntimeError(label)
def occ(*args):
 p=subprocess.run(['sudo','-n','docker','exec','-u','www-data','-e','OC_PASS='+json.loads((root/'credentials.json').read_text())['password'],container,'php','occ',*args],capture_output=True,text=True)
 if p.returncode:raise RuntimeError('OCC test setup failed: '+args[0])
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kw):return None
class Client:
 def __init__(self):self.cookies=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies),NoRedirect());self.token=''
 def call(self,path,data=None,headers=None,method=None):
  h=headers or {};payload=None
  if data is not None:
   payload=json.dumps(data).encode();h={'Content-Type':'application/json',**h}
  req=urllib.request.Request(base+path,data=payload,headers=h,method=method or ('POST' if data is not None else 'GET'))
  try:r=self.http.open(req,timeout=35)
  except urllib.error.HTTPError as e:r=e
  return r.code,r.headers,r.read().decode()
 def login(self,user):
  c,h,s=self.call('/login');m=re.search('data-requesttoken="([^"]+)"',s);assert m
  token=html.unescape(m[1]);pw=json.loads((root/'credentials.json').read_text())['password']
  req=urllib.request.Request(base+'/login',data=urllib.parse.urlencode({'user':user,'password':pw,'requesttoken':token}).encode(),headers={'Content-Type':'application/x-www-form-urlencoded','Origin':base})
  try:r=self.http.open(req)
  except urllib.error.HTTPError as e:r=e
  check(user+' login',r.code in [302,303] and not r.headers.get('Location','').startswith('/login'));r.read()
  c,h,s=self.call('/index.php/apps/placetel/')
  if c!=200 or 'placetel-root' not in s:
   print('APP_PAGE_STATUS',c,'LOCATION',h.get('Location',''),flush=True)
   code='$f="/var/www/html/data/nextcloud.log";if(is_file($f)){foreach(array_slice(file($f),-8) as $line){$j=json_decode($line,true);echo substr(preg_replace("/fixture[-A-Za-z0-9]+/", "[redacted]", (string)($j["message"]??"")),0,350).PHP_EOL;}}'
   p=subprocess.run(['sudo','-n','docker','exec',container,'php','-r',code],capture_output=True,text=True);print(p.stdout,flush=True)
  check(user+' app page',c==200 and 'placetel-root' in s)
  self.token=html.unescape(re.search('data-requesttoken="([^"]+)"',s)[1]);self.page=s
  return s
 def api(self,path,data=None,headers=None,method=None):return self.call('/index.php/apps/placetel/api/'+path,data,{'requesttoken':self.token,**(headers or {})},method)
try:
 admin=Client();admin.login('admin');viewer=Client();viewer.login('viewer');operator=Client();operator.login('operator')
 check('demo bootstrap seeds synthetic data',admin.api('bootstrap')[0]==200)
 check('admin configures legacy operator group',admin.api('settings',{'operator_groups':['telefonie-disposition'],'viewer_groups':None})[0]==200)
 from datetime import datetime, timedelta
 from zoneinfo import ZoneInfo
 day=(datetime.now(ZoneInfo('Europe/Berlin'))-timedelta(days=1)).strftime('%Y-%m-%d')
 c,h,b=operator.api('operations/report?from='+day+'&to='+day);check('operator can read demo callback report',c==200);report=json.loads(b)
 candidate=next(c for c in report['callbacks'] if c['state']=='open');payload={'callbacks':[{'key':candidate['key'],'latestId':candidate['latestId']}]}
 check('viewer cannot bulk complete callbacks',viewer.api('operations/callbacks/complete',payload)[0]==403)
 check('operator can bulk complete callback',operator.api('operations/callbacks/complete',payload)[0]==200)
 check('completed callback retained',next(x for x in json.loads(operator.api('operations/report?from='+day+'&to='+day)[2])['callbacks'] if x['key']==candidate['key'])['state']=='done')
 c,h,b=admin.api('permissions/options?search=viewer');choices=json.loads(b);check('admin searches Nextcloud users',c==200 and any(x['id']=='viewer' for x in choices['users']))
 check('viewer cannot list users',viewer.api('permissions/options')[0]==403)
 areas=['overview','agents','callbacks','routing','events','queues','schedule','statistics','settings'];none={area:'none' for area in areas};viewer_rights={**none,'statistics':'read'}
 matrix={'management_groups':['telefonie-disposition'],'matrix':{'default':none,'groups':{},'users':{'viewer':viewer_rights}}}
 check('admin configures management and user rights',admin.api('settings',matrix)[0]==200)
 c,h,b=viewer.api('bootstrap');data=json.loads(b);check('restricted bootstrap hides denied sections',c==200 and data['hosting']['permissions']['callbacks']=='none' and 'queueSnapshot' not in data and data['operations']['notify']['calls']==[])
 check('individual statistics read works',viewer.api('statistics?from='+day+'&to='+day)[0]==200)
 check('forbidden callback report rejected',viewer.api('operations/report?from='+day+'&to='+day)[0]==403)
 check('forbidden overview scope rejected',viewer.api('statistics?scope=overview')[0]==403)
 check('management group retains full access',json.loads(operator.api('bootstrap')[2])['hosting']['role']=='admin')
 check('management cannot remove own group',operator.api('settings',{**matrix,'management_groups':[]})[0]==400)
 check('admin retains full access',json.loads(admin.api('bootstrap')[2])['hosting']['role']=='admin')
 occ('group:removeuser','telefonie-disposition','operator')
 check('revoked management group loses access',operator.api('bootstrap')[0]==403)
 print('RIGHTS_HTTP_COMPLETE',flush=True)
finally:
 (root/'rights-http-results.json').write_text(json.dumps({'checks':checks,'count':len(checks),'passed':sum(x['passed'] for x in checks)},indent=2))
