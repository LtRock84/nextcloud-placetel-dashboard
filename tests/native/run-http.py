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
  c,h,s=self.call('/index.php/apps/placetel/');check(user+' app page',c==200 and 'placetel-root' in s)
  self.token=html.unescape(re.search('data-requesttoken="([^"]+)"',s)[1]);self.page=s
  return s
 def api(self,path,data=None,headers=None,method=None):return self.call('/index.php/apps/placetel/api/'+path,data,{'requesttoken':self.token,**(headers or {})},method)
try:
 anon=Client();c,h,b=anon.api('bootstrap');check('anonymous cannot read app data',c in [302,303,401,403] and '"agents"' not in b)
 admin=Client();admin.login('admin')
 check('native runtime without Node backend',json.loads(admin.api('health')[2])['native'] is True)
 c,h,b=admin.api('settings',{'operator_groups':['telefonie-disposition'],'viewer_groups':None});check('admin configures existing Nextcloud groups',c==200)
 clients={'admin':admin}
 for user,role in [('admin','admin'),('viewer','viewer'),('operator','operator')]:
  client=clients.get(user) or Client()
  if user!='admin':client.login(user)
  clients[user]=client
  c,h,b=client.api('bootstrap');check(user+' native bootstrap',c==200)
  data=json.loads(b);check(user+' identity and native runtime',data['hosting']['role']==role and data['hosting']['user']==user and data['hosting']['native'])
  check(user+' unsupported assignment planning disabled',data['status']['capabilities']['assignments'] is False)
  check(user+' no API key required for isolated demo',data['status']['mode']=='demo' and data['hosting']['configured'] is False)
 viewer=clients['viewer'];operator=clients['operator']
 check('statistics and hourly history',viewer.api('statistics?from=2026-10-19&to=2026-10-20')[0]==200)
 check('coverage records',viewer.api('coverage')[0]==200)
 check('callbacks and reachability report',viewer.api('operations/report?from=2026-10-19&to=2026-10-20')[0]==200)
 check('CSRF required on reads',viewer.api('bootstrap',headers={'requesttoken':''})[0]==412)
 check('incorrect CSRF rejected',viewer.api('bootstrap',headers={'requesttoken':'bad'})[0]==412)
 check('OCS bypass rejected',viewer.api('bootstrap',headers={'OCS-APIRequest':'true'})[0]==403)
 check('viewer cannot change agent',viewer.api('agents/a1',{'status':'pause'})[0]==403)
 check('forged browser role has no effect',viewer.api('agents/a1',{'status':'pause'},headers={'X-Placetel-Role':'admin'})[0]==403)
 check('operator can change demo agent',operator.api('agents/a1',{'status':'pause'})[0]==200)
 data=json.loads(viewer.api('bootstrap')[2]);check('demo agent change stored',next(a for a in data['agents'] if a['id']=='a1')['status']=='pause')
 check('operator cannot set credentials',operator.api('connect',{'token':'x'})[0]==403)
 check('malformed key rejected without external request',admin.api('connect',{'token':'x'})[0]==400)
 check('live mode needs configured account',admin.api('mode',{'mode':'live'})[0]==400)
 check('assignment endpoint disabled even for admin',admin.api('assignments',{'updates':[]})[0]==403)
 check('unsupported endpoint rejected',admin.api('shutdown',{})[0]==403)
 check('DispoLive optional and initially off',json.loads(admin.api('bootstrap')[2])['dispolive']['enabled'] is False)
 key='synthetic-dispolive-no-real-access'
 check('optional key can be saved in app settings',admin.api('dispolive/connect',{'origin':'https://fixture.dispolive.de','token':key})[0]==200)
 data=json.loads(admin.api('bootstrap')[2]);check('saving addon key does not enable it',not data['dispolive']['enabled'] and data['dispolive']['configured'])
 check('addon key absent from API response',key not in json.dumps(data))
 check('addon switch on allowed for admin',admin.api('dispolive/toggle',{'enabled':True})[0]==200)
 check('addon switch off allowed for admin',admin.api('dispolive/toggle',{'enabled':False})[0]==200)
 check('viewer cannot enable addon',viewer.api('dispolive/toggle',{'enabled':True})[0]==403)
 check('addon disconnect removes stored key',admin.api('dispolive/disconnect',{})[0]==200 and not json.loads(admin.api('bootstrap')[2])['dispolive']['configured'])
 payload={'enabled':False,'desktopPhone':'100','firmenKrz':'','calledNumbers':[]}
 check('viewer cannot configure notification side effects',viewer.api('dispolive/popups',payload)[0]==403)
 check('operator cannot enable notification side effects',operator.api('dispolive/popups',payload)[0]==403)
 check('admin can save disabled popup configuration',admin.api('dispolive/popups',payload)[0]==200)
 check('popup activation requires live setup',admin.api('dispolive/popups',{**payload,'enabled':True})[0]==400)
 check('popup state visible and defaults off',json.loads(admin.api('bootstrap')[2])['dispolive']['popups']['enabled'] is False)
 check('operator loads demo routing catalog',operator.api('operations/routing/load',{})[0]==200)
 c,h,b=operator.api('operations/routing/profile',{'label':'Synthetic route','routingId':'100','planId':'201','activateAt':None});check('demo routing template saved',c==200);profile=json.loads(b)
 c,h,b=operator.api('operations/routing/preview',{'id':profile['id']});check('routing preview provided',c==200);preview=json.loads(b)
 check('manual routing apply in demo',operator.api('operations/routing/apply',{'id':profile['id'],'token':preview['token']})[0]==200)
 check('routing preview token cannot be reused',operator.api('operations/routing/apply',{'id':profile['id'],'token':preview['token']})[0]==400)
 check('alert thresholds stored',operator.api('operations/alerts',{'warningSeconds':45,'criticalSeconds':90,'sound':False})[0]==200)
 report=json.loads(operator.api('operations/report?from=2026-10-19&to=2026-10-20')[2]);candidate=report['callbacks'][0]
 check('callback note saved in native database',operator.api('operations/callback',{'key':candidate['key'],'latestId':candidate['latestId'],'state':'done','note':'Synthetic follow-up'})[0]==200)
 report=json.loads(operator.api('operations/report?from=2026-10-19&to=2026-10-20')[2]);check('callback note visible across users',next(c for c in report['callbacks'] if c['key']==candidate['key'])['note']=='Synthetic follow-up')
 occ('app:disable','placetel');occ('app:enable','placetel');check('stored callback survives disable and enable',next(c for c in json.loads(operator.api('operations/report?from=2026-10-19&to=2026-10-20')[2])['callbacks'] if c['key']==candidate['key'])['state']=='done')
 check('unknown group rejected',admin.api('settings',{'operator_groups':['missing-synthetic-group'],'viewer_groups':None})[0]==400)
 c,h,b=viewer.call('/custom_apps/placetel/js/main.js');check('compiled native UI served',c==200 and len(b)>100000)
 check('authenticated support document explains key protection','Schlüssel' in viewer.call('/index.php/apps/placetel/help/fehler-melden')[2])
 import hmac,hashlib
 raw=urllib.parse.urlencode({'call_id':'d'*64,'event':'IncomingCall','direction':'in','from':'+493012345678','to':'100'})
 signature=hmac.new(b'fixture-native-notify-signature-secret',raw.encode(),hashlib.sha256).hexdigest()
 def notify(sig):
  req=urllib.request.Request(base+'/index.php/apps/placetel/notify',data=raw.encode(),headers={'Content-Type':'application/x-www-form-urlencoded','X-Placetel-Signature':sig})
  try:r=anon.http.open(req,timeout=10)
  except urllib.error.HTTPError as e:r=e
  return r.code
 check('public callback rejects bad HMAC',notify('0'*64)==401)
 check('public callback accepts valid HMAC without login',notify(signature)==200)
 check('callback duplicate acknowledged idempotently',notify(signature)==200)
 # A slow ordinary import must not block the independent webhook receiver.
 code='define("OC_CONSOLE",true);require "/var/www/html/lib/base.php";$l=\\OCP\\Server::get(\\OCP\\Lock\\ILockingProvider::class);$l->acquireLock("placetel-dashboard-native",\\OCP\\Lock\\ILockingProvider::LOCK_EXCLUSIVE);echo "LOCK_HELD".PHP_EOL;flush();usleep(1500000);$l->releaseLock("placetel-dashboard-native",\\OCP\\Lock\\ILockingProvider::LOCK_EXCLUSIVE);'
 hold=subprocess.Popen(['sudo','-n','docker','exec','-u','www-data',container,'php','-r',code],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
 assert hold.stdout.readline().strip()=='LOCK_HELD'
 check('ordinary import lock does not block signed event reception',notify(signature)==200)
 assert hold.wait(timeout=5)==0

 occ('group:removeuser','telefonie-disposition','operator');check('group revocation immediate',operator.api('agents/a1',{'status':'online'})[0]==403)
 check('admin can restrict reading to groups',admin.api('settings',{'operator_groups':[],'viewer_groups':[]})[0]==200)
 check('reader loses access after group restriction',viewer.api('bootstrap')[0]==403)
 check('admin retains access',admin.api('bootstrap')[0]==200)
 admin.api('settings',{'operator_groups':[],'viewer_groups':None})
 viewer.call('/logout?'+urllib.parse.urlencode({'requesttoken':viewer.token}));check('logged-out client cannot read',viewer.api('bootstrap')[0] in [302,303,401,403])
 print('NATIVE_HTTP_COMPLETE',flush=True)
finally:
 (root/'native-http-results.json').write_text(json.dumps({'checks':checks,'count':len(checks),'passed':sum(x['passed'] for x in checks)},indent=2))
