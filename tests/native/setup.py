import os,pathlib,subprocess,secrets,time,urllib.request,json
if os.environ.get('PLACETEL_DISPOSABLE_TEST')!='1':raise SystemExit('Nur in expliziter Wegwerf-Testinstanz ausführen.')
r=pathlib.Path(os.environ.get('PLACETEL_NATIVE_FIXTURE_DIR','/tmp/placetel-native-20261020'));r.mkdir(exist_ok=False)
prefix=os.environ.get('PLACETEL_NATIVE_NAME','placetel-native');port=os.environ.get('PLACETEL_NATIVE_PORT','44326');image=os.environ.get('PLACETEL_NATIVE_IMAGE','nextcloud:35.0.2-apache')
def docker(*args):
 p=subprocess.run(['sudo','-n','docker',*args],capture_output=True,text=True)
 if p.returncode:print(p.stderr[-1200:],flush=True);raise RuntimeError('Native fixture step failed: '+args[0])
 return p.stdout.strip()
pw=secrets.token_urlsafe(24);(r/'credentials.json').write_text(json.dumps({'password':pw}));(r/'pg.env').write_text('POSTGRES_DB=nextcloud\nPOSTGRES_USER=nextcloud\nPOSTGRES_PASSWORD='+pw+'\n');(r/'nc.env').write_text('POSTGRES_HOST='+prefix+'-db\nPOSTGRES_DB=nextcloud\nPOSTGRES_USER=nextcloud\nPOSTGRES_PASSWORD='+pw+'\nNEXTCLOUD_ADMIN_USER=admin\nNEXTCLOUD_ADMIN_PASSWORD='+pw+'\nNEXTCLOUD_TRUSTED_DOMAINS=127.0.0.1 localhost\nPHP_MEMORY_LIMIT=512M\n')
for n in ['credentials.json','pg.env','nc.env']:(r/n).chmod(0o600)
docker('network','create',prefix+'-test')
docker('run','-d','--name',prefix+'-db','--network',prefix+'-test','--env-file',str(r/'pg.env'),'postgres:18.6')
docker('run','-d','--name',prefix+'-nc','--network',prefix+'-test','-p','127.0.0.1:'+port+':80','--env-file',str(r/'nc.env'),image)
print('Warte auf frische Nextcloud ohne Node-Backend',flush=True)
for i in range(150):
 try:
  j=json.load(urllib.request.urlopen('http://127.0.0.1:'+port+'/status.php',timeout=2))
  if j.get('installed') and not j.get('maintenance'):break
 except Exception:pass
 time.sleep(2)
else:raise RuntimeError('Nextcloud not ready')
subprocess.run(['tar','-xzf','/tmp/placetel-native-app-test.tar.gz','-C',str(r)],check=True)
docker('cp',str(r/'placetel'),prefix+'-nc:/var/www/html/custom_apps/placetel');docker('exec',prefix+'-nc','chown','-R','www-data:www-data','/var/www/html/custom_apps/placetel')
print(docker('exec','-u','www-data',prefix+'-nc','php','occ','app:enable','placetel'),flush=True)
for user in ['viewer','operator']:docker('exec','-u','www-data','-e','OC_PASS='+pw,prefix+'-nc','php','occ','user:add','--password-from-env',user)
docker('exec','-u','www-data',prefix+'-nc','php','occ','group:add','telefonie-disposition');docker('exec','-u','www-data',prefix+'-nc','php','occ','group:adduser','telefonie-disposition','operator')
print('Native App mit Datenbankmigration installiert; kein Node, Gateway oder Secretmount vorhanden.',flush=True)
