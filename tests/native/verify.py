import os,subprocess,json,pathlib,hashlib,tarfile,time
if os.environ.get('PLACETEL_DISPOSABLE_TEST')!='1':raise SystemExit('Nur in expliziter Wegwerf-Testinstanz ausführen.')
prefix=os.environ.get('PLACETEL_NATIVE_NAME','placetel-native');root=pathlib.Path(os.environ.get('PLACETEL_NATIVE_FIXTURE_DIR','/tmp/placetel-native-20261020'));container=prefix+'-nc'
def docker(*args):
 p=subprocess.run(['sudo','-n','docker',*args],capture_output=True,text=True)
 if p.returncode:print(p.stderr[-1500:]);raise RuntimeError('Fixture operation failed '+args[0])
 return p.stdout
# Only a deliberately named disposable synthetic fixture is allowed.
assert prefix in ['placetel-native','placetel-native351'] and root.name.startswith(prefix)
stage=root/'final-app';stage.mkdir(exist_ok=True)
with tarfile.open('/tmp/placetel-native-app-test.tar.gz') as t:t.extractall(stage,filter='data')
docker('exec',container,'rm','-rf','/var/www/html/custom_apps/placetel')
docker('cp',str(stage/'placetel'),container+':/var/www/html/custom_apps/')
docker('exec',container,'chown','-R','www-data:www-data','/var/www/html/custom_apps/placetel')
for name,target in [('contract.php','native-contract.php'),('native-parity.json','native-parity.json')]:docker('cp','/tmp/'+name,container+':/tmp/'+target);docker('exec',container,'chown','www-data:www-data','/tmp/'+target)
out=docker('exec','-u','www-data','-e','PLACETEL_DISPOSABLE_TEST=1',container,'php','/tmp/native-contract.php');print(out,flush=True);assert 'NATIVE_CONTRACT_COMPLETE' in out
result=json.loads(docker('exec',container,'cat','/tmp/native-contract-results.json'));assert result['count']==result['passed']==40;(root/'native-contract-results.json').write_text(json.dumps(result,indent=2))
docker('exec','-u','www-data',container,'php','occ','group:adduser','telefonie-disposition','operator')
env={**os.environ,'PLACETEL_DISPOSABLE_TEST':'1'};p=subprocess.run(['python3','/tmp/run-http.py'],env=env);assert p.returncode==0
http=json.loads((root/'native-http-results.json').read_text());assert http['count']==http['passed']==70
docker('cp','/tmp/popups.php',container+':/tmp/native-popups.php');docker('exec',container,'chown','www-data:www-data','/tmp/native-popups.php')
out=docker('exec','-u','www-data','-e','PLACETEL_DISPOSABLE_TEST=1',container,'php','/tmp/native-popups.php');print(out,flush=True);assert 'NATIVE_POPUPS_COMPLETE' in out
result=json.loads(docker('exec',container,'cat','/tmp/native-popup-results.json'));assert result['count']==result['passed']==40;(root/'native-popup-results.json').write_text(json.dumps(result,indent=2))
for file in ['webhook-contract.php','webhook-router.php']:docker('cp','/tmp/'+file,container+':/tmp/'+file);docker('exec',container,'chown','www-data:www-data','/tmp/'+file)
out=docker('exec','-u','www-data','-e','PLACETEL_DISPOSABLE_TEST=1',container,'php','/tmp/webhook-contract.php');print(out,flush=True);assert 'NATIVE_WEBHOOK_COMPLETE' in out
result=json.loads(docker('exec',container,'cat','/tmp/native-webhook-results.json'));assert result['count']==result['passed']==7;(root/'native-webhook-results.json').write_text(json.dumps(result,indent=2))
assert 'Popup-Warteschlange geprüft.' in docker('exec','-u','www-data',container,'php','occ','placetel:notifications:work')
# Instantiate and execute the registered public Nextcloud background job, with demo only.
code='define("OC_CONSOLE",true);require "/var/www/html/lib/base.php";$a=new \\OCP\\AppFramework\\App("placetel");$j=$a->getContainer()->get(\\OCA\\Placetel\\BackgroundJob\\Synchronize::class);$r=new \\ReflectionMethod($j,"run");$r->invoke($j,null);echo "NATIVE_JOB_COMPLETE";'
assert 'NATIVE_JOB_COMPLETE' in docker('exec','-u','www-data',container,'php','-r',code)
# Record installed-file hashes and versions, never fixture credentials or vendor data.
code='$r="/var/www/html/custom_apps/placetel/";$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if($f->isFile())$o[substr($f->getPathname(),strlen($r))]=hash_file("sha256",$f->getPathname());ksort($o);echo json_encode($o);'
hashes=json.loads(docker('exec',container,'php','-r',code));expected={str(p.relative_to(stage/'placetel')):hashlib.sha256(p.read_bytes()).hexdigest() for p in (stage/'placetel').rglob('*') if p.is_file()};assert hashes==expected
status=json.loads(docker('exec','-u','www-data',container,'php','occ','status','--output=json'))
(root/'native-environment-results.json').write_text(json.dumps({'nextcloud':status['versionstring'],'php':docker('exec',container,'php','-r','echo PHP_VERSION;').strip(),'nodeBackend':False,'nativeJobExecuted':True,'installedFileCount':len(hashes),'installedMatchesPackage':True,'hashes':hashes},indent=2))
print('NATIVE_FINAL_COMPLETE: contract40 HTTP70 popup-contract background-job and packaged-file parity passed',flush=True)
