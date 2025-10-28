<?php

// https://github.com/PSA-dgi

$dbFile = __DIR__ . '/chat.sqlite';
$uploadsDir = __DIR__ . '/uploads';
$maxFileSize = 8*1024*1024;
$allowedMimeStarts=['image/','video/','audio/','text/','application/pdf'];
if(!file_exists($uploadsDir)) mkdir($uploadsDir,0755,true);

try{
    $db=new PDO('sqlite:'.$dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        message TEXT,
        filename TEXT,
        original_name TEXT,
        created_at INTEGER NOT NULL,
        nonce TEXT
    )");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_nonce ON messages(nonce)");
}catch(Exception $e){ die('DB error: '.htmlspecialchars($e->getMessage())); }

function clean_username($u){ $u=trim($u); if($u==='') return 'Guest'; return mb_substr(preg_replace('/[^\p{L}\p{N}_\- ]/u','',$u),0,32); }

$action=$_REQUEST['action']??null;
if($action==='send'){
    $username=clean_username($_POST['username']??'Guest');
    $message=trim($_POST['message']??'');
    $nonce=$_POST['nonce']??bin2hex(random_bytes(8));
    $filename=null;$originalName=null;
    if(!empty($_FILES['file'])&&$_FILES['file']['error']!==UPLOAD_ERR_NO_FILE){
        $f=$_FILES['file'];
        if($f['error']!==UPLOAD_ERR_OK){echo json_encode(['ok'=>false,'error'=>'Upload failed']);exit;}
        if($f['size']>$maxFileSize){echo json_encode(['ok'=>false,'error'=>'File too large']);exit;}
        $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$f['tmp_name']);finfo_close($finfo);
        $allowed=false; foreach($allowedMimeStarts as $start){if(strpos($mime,$start)===0){$allowed=true;break;}}
        if(!$allowed){echo json_encode(['ok'=>false,'error'=>'Disallowed file type']);exit;}
        $ext=pathinfo($f['name'],PATHINFO_EXTENSION); $safe=bin2hex(random_bytes(12)).($ext?'.'.$ext:'');
        if(!move_uploaded_file($f['tmp_name'],$uploadsDir.'/'.$safe)){echo json_encode(['ok'=>false,'error'=>'Move failed']);exit;}
        $filename=$safe;$originalName=$f['name'];
    }
    $check=$db->prepare('SELECT 1 FROM messages WHERE nonce=?'); $check->execute([$nonce]);
    if($check->fetch()){echo json_encode(['ok'=>true,'duplicate'=>true]);exit;}
    $stmt=$db->prepare('INSERT INTO messages(username,message,filename,original_name,created_at,nonce)VALUES(?,?,?,?,?,?)');
    $stmt->execute([$username,$message,$filename,$originalName,time(),$nonce]);
    echo json_encode(['ok'=>true]); exit;
}

if($action==='fetch'){
    $since=intval($_GET['since']??0);
    $stmt=$db->prepare('SELECT * FROM messages WHERE id>? ORDER BY id ASC');
    $stmt->execute([$since]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$r){$r['created_at']=intval($r['created_at']);}
    echo json_encode(['ok'=>true,'messages'=>$rows]); exit;
}

if($action==='download'&&!empty($_GET['file'])){
    $file=basename($_GET['file']);$path=$uploadsDir.'/'.$file; if(!file_exists($path)){http_response_code(404);exit('Not found');}
    $name=basename($_GET['name']??$file);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    readfile($path); exit;
}

if($action==='clear'){
    $db->exec('DELETE FROM messages');
    echo json_encode(['ok'=>true]);
    exit;
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QCHAT</title>
<style>
body{margin:0;background:#0b0f16;color:#e6eef8;font-family:Inter,Segoe UI,Roboto,Arial;display:flex;justify-content:center;align-items:center;height:100vh;padding:10px}
.app{width:100%;max-width:900px;height:85vh;background:#0f1720;border-radius:14px;box-shadow:0 8px 25px rgba(0,0,0,0.5);display:flex;flex-direction:column;overflow:hidden}
.header{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.05);font-weight:600;display:flex;justify-content:space-between;align-items:center}
.messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth}
.msg{max-width:75%;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,0.05);backdrop-filter:blur(6px)}
.msg.me{align-self:flex-end;background:linear-gradient(135deg,rgba(124,92,255,0.2),rgba(124,92,255,0.08))}
.meta{font-size:12px;color:#9aa4b2;margin-bottom:4px}
.controls{display:flex;gap:6px;padding:6px;border-top:1px solid rgba(255,255,255,0.05)}
input,button,label{font-family:inherit}
#msg{flex:1;padding:8px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:inherit;font-size:14px}
#username{width:80px;padding:6px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:inherit;font-size:14px}
.btn{padding:8px 12px;border:none;border-radius:10px;background:linear-gradient(90deg,#7c5cff,#5ba0ff);font-weight:600;color:#0b0f16;cursor:pointer;transition:transform 0.1s ease}
.btn:active{transform:scale(0.95)}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:6px}

@media(max-width:700px){
    .app{width:100%;height:100vh;border-radius:0;box-shadow:none}
    .messages{padding:12px;}
    .controls{position:fixed;bottom:0;left:0;right:0;padding:6px;background:rgba(15,23,32,0.95);gap:4px}
    #msg{font-size:14px;padding:0.1px;border-radius:8px}
    #username{width:70px;font-size:13px;padding:4px}
    label[for="fileInput"],#sendBtn{padding:8px 10px;font-size:14px}
    .messages{padding-bottom:60px}
}
</style>
</head>
<body>
<div class="app">
<div class="header">QCHAT 💬<button id="clearBtn" class="btn">🗑️ Clear Chat</button></div>
<div id="messages" class="messages"></div>
<div class="controls">
<input id="username" value="Guest" placeholder="Name">
<input id="msg" placeholder="Type...">
<label class="btn" for="fileInput">📁</label>
<input id="fileInput" type="file" style="display:none">
<button id="sendBtn" class="btn">Send</button>
</div>
</div>
<script>
let lastId=0;
const msgWrap=document.getElementById('messages');
const sendBtn=document.getElementById('sendBtn');
const msgInput=document.getElementById('msg');
const fileInput=document.getElementById('fileInput');
const usernameInput=document.getElementById('username');
const clearBtn=document.getElementById('clearBtn');

function esc(s){return String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}

function renderMessage(m){
    const el=document.createElement('div');
    el.className='msg'+(m.username===usernameInput.value?' me':'');
    const time=new Date(m.created_at*1000);
    let html=`<div class="meta"><strong>${esc(m.username)}</strong> · ${time.toLocaleTimeString()}</div>`;
    if(m.message) html+=`<div>${esc(m.message)}</div>`;
    if(m.filename){
        const url=`?action=download&file=${encodeURIComponent(m.filename)}&name=${encodeURIComponent(m.original_name)}`;
        const ext=(m.original_name||'').split('.').pop().toLowerCase();
        if(['png','jpg','jpeg','gif','webp','bmp'].includes(ext)){
            html+=`<div class="file-link"><a href="${url}" target="_blank">📷 ${esc(m.original_name)}</a></div>`;
            html+=`<div style="margin-top:8px"><img src="${url}" style="max-width:240px;border-radius:8px;display:block"></div>`;
        } else html+=`<div class="file-link"><a href="${url}">📄 ${esc(m.original_name||m.filename)}</a></div>`;
    }
    el.innerHTML=html; return el;
}

async function fetchLoop(){
    try{
        const r=await fetch(`?action=fetch&since=${lastId}`);
        const j=await r.json();
        if(j.ok){
            for(const m of j.messages){
                msgWrap.appendChild(renderMessage(m));
                lastId=Math.max(lastId,m.id);
            }
            if(j.messages.length) msgWrap.scrollTop=msgWrap.scrollHeight;
        }
    }catch(e){console.error('fetch err',e);}
}
setInterval(fetchLoop,1200); fetchLoop();

async function sendMessage(){
    const username=usernameInput.value.trim()||'Guest';
    const message=msgInput.value.trim();
    if(!message&&!fileInput.files.length) return;
    const form=new FormData();
    form.append('action','send');
    form.append('username',username);
    form.append('message',message);
    if(fileInput.files.length) form.append('file',fileInput.files[0]);
    sendBtn.disabled=true; sendBtn.textContent='Sending...';
    try{
        const res=await fetch('.',{method:'POST',body:form});
        const j=await res.json();
        if(j.ok){msgInput.value=''; fileInput.value=''; await fetchLoop();}
        else alert('Error: '+(j.error||'Unknown'));
    }catch(e){alert('Network error'); console.error(e);}
    sendBtn.disabled=false; sendBtn.textContent='Send';
}

sendBtn.addEventListener('click',sendMessage);
msgInput.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey) sendMessage();});
clearBtn.addEventListener('click',async()=>{
    if(!confirm('Clear all messages?')) return;
    try{
        const res=await fetch('?action=clear'); const j=await res.json();
        if(j.ok){msgWrap.innerHTML=''; lastId=0;}
        else alert('Failed to clear chat');
    }catch(e){alert('Network error');}
});
</script>
</body>
</html>
